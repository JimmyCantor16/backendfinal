<?php

namespace App\Console\Commands;

use App\Models\CashRegister;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\InvoiceService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimulateDemoDay extends Command
{
    protected $signature = 'demo:simulate
                            {--orders=50 : número de órdenes POS a generar}
                            {--invoices=15 : número de facturas a generar}
                            {--opening=200000 : monto de apertura de caja en COP}
                            {--date= : fecha de simulación (YYYY-MM-DD), default hoy}
                            {--user-id=1 : ID del usuario operador}
                            {--reset : limpiar datos de simulación previa del día}';

    protected $description = 'Simula 12h de operación POS (8am-8pm) — ventas, facturas, cierre de caja';

    public function handle(
        OrderService $orderSvc,
        InvoiceService $invoiceSvc,
        CashRegisterService $cashSvc
    ): int {
        $userId = (int) $this->option('user-id');
        $user = User::find($userId);
        if (!$user) {
            $this->error("Usuario {$userId} no existe");
            return self::FAILURE;
        }

        // Autenticar: services usan auth()->user()->business_id y BelongsToBusiness trait
        Auth::login($user);

        $date = $this->option('date') ?: Carbon::today()->toDateString();
        $dayStart = Carbon::parse($date)->setTime(8, 0);
        $dayEnd = Carbon::parse($date)->setTime(20, 0);

        if ($this->option('reset')) {
            $this->resetDay($date, $userId);
        }

        // Boost stock para garantizar la simulación
        $this->info('Asegurando stock alto en productos activos...');
        Product::where('is_active', true)
            ->where('business_id', $user->business_id)
            ->update(['stock' => DB::raw('GREATEST(stock, 5000)')]);

        // === Abrir caja a las 8:00 AM ===
        Carbon::setTestNow($dayStart);
        $this->info("--- Abriendo caja a las {$dayStart->format('H:i')} (apertura: \$".number_format((float)$this->option('opening'), 0, ',', '.').") ---");
        $cash = $cashSvc->open($userId, (float) $this->option('opening'));

        // Cargar pool de productos y clientes
        $products = Product::where('is_active', true)
            ->where('business_id', $user->business_id)
            ->get();
        $clients = Client::where('business_id', $user->business_id)->get();

        if ($products->isEmpty()) {
            $this->error('No hay productos activos para este business.');
            return self::FAILURE;
        }

        $orderCount = (int) $this->option('orders');
        $invoiceCount = (int) $this->option('invoices');
        $totalEvents = $orderCount + $invoiceCount;

        // Distribuir eventos en 12h con jitter
        $totalMinutes = 12 * 60;
        $minutesPerEvent = (int) max(1, intdiv($totalMinutes, max(1, $totalEvents)));

        // Mezcla aleatoria del calendario del día
        $events = array_merge(
            array_fill(0, $orderCount, 'order'),
            array_fill(0, $invoiceCount, 'invoice')
        );
        shuffle($events);

        // Distribución de métodos de pago (~ 50% cash, 30% card, 15% transfer, 5% qr)
        $paymentBag = array_merge(
            array_fill(0, 50, 'cash'),
            array_fill(0, 30, 'card'),
            array_fill(0, 15, 'transfer'),
            array_fill(0, 5, 'qr'),
        );

        $stats = [
            'orders_ok' => 0,
            'orders_cancelled' => 0,
            'orders_failed' => 0,
            'invoices_ok' => 0,
            'invoices_failed' => 0,
            'items_sold' => 0,
        ];

        $bar = $this->output->createProgressBar($totalEvents);
        $bar->start();

        $now = $dayStart->copy();
        foreach ($events as $i => $type) {
            // Avanzar reloj con jitter (±40% del intervalo medio)
            $jitter = (int) (rand(-40, 40) * $minutesPerEvent / 100);
            $now = $now->copy()->addMinutes($minutesPerEvent + $jitter);
            if ($now->gte($dayEnd)) {
                break;
            }
            Carbon::setTestNow($now);

            try {
                if ($type === 'order') {
                    $this->processOrder($orderSvc, $products, $paymentBag, $userId, $stats);
                } else {
                    $this->processInvoice($invoiceSvc, $products, $clients, $userId, $stats);
                }
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("Evento #{$i} ({$type}) falló: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // === Cerrar caja a las 8:00 PM ===
        Carbon::setTestNow($dayEnd);
        $this->info("--- Cerrando caja a las {$dayEnd->format('H:i')} ---");
        $cashSvc->close($cash);
        $cash->refresh();

        Carbon::setTestNow();

        $this->renderReport($cash, $date, $stats);

        return self::SUCCESS;
    }

    private function processOrder(OrderService $svc, $products, array $paymentBag, int $userId, array &$stats): void
    {
        $order = $svc->createOrder($userId);

        $itemCount = rand(1, 5);
        $itemsAdded = 0;
        for ($k = 0; $k < $itemCount; $k++) {
            $p = $products->random();
            $qty = rand(1, 3);
            try {
                $svc->addItem($order, $p->id, $qty, $userId);
                $itemsAdded++;
                $stats['items_sold'] += $qty;
            } catch (\InvalidArgumentException $e) {
                // skip on stock issue
            }
        }

        if ($itemsAdded === 0) {
            // Orden vacía no se puede cerrar — cancelar
            $svc->cancelOrder($order, $userId);
            $stats['orders_failed']++;
            return;
        }

        // 5% cancela, 95% cierra
        if (rand(1, 100) <= 5) {
            $svc->cancelOrder($order, $userId);
            $stats['orders_cancelled']++;
        } else {
            $method = $paymentBag[array_rand($paymentBag)];
            $svc->closeOrder($order, $method);
            $stats['orders_ok']++;
        }
    }

    private function processInvoice(InvoiceService $svc, $products, $clients, int $userId, array &$stats): void
    {
        $items = [];
        $itemCount = rand(1, 4);
        for ($k = 0; $k < $itemCount; $k++) {
            $p = $products->random();
            $items[] = ['product_id' => $p->id, 'quantity' => rand(1, 3)];
        }

        $data = [
            'items' => $items,
            'client_id' => $clients->isNotEmpty() ? $clients->random()->id : null,
        ];

        try {
            $svc->createInvoice($data, $userId);
            $stats['invoices_ok']++;
        } catch (\Throwable $e) {
            $stats['invoices_failed']++;
        }
    }

    private function resetDay(string $date, int $userId): void
    {
        $this->warn("Limpiando simulación previa del {$date}...");
        DB::transaction(function () use ($date, $userId) {
            $orderIds = Order::whereDate('opened_at', $date)->pluck('id');
            $invoiceIds = Invoice::whereDate('created_at', $date)->pluck('id');

            DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
            DB::table('invoice_items')->whereIn('invoice_id', $invoiceIds)->delete();
            InventoryMovement::whereDate('created_at', $date)->delete();
            Order::whereIn('id', $orderIds)->delete();
            Invoice::whereIn('id', $invoiceIds)->delete();
            CashRegister::where('user_id', $userId)
                ->whereDate('opened_at', $date)
                ->delete();
        });
    }

    private function renderReport(CashRegister $cash, string $date, array $stats): void
    {
        $this->newLine();
        $this->line("<fg=cyan;options=bold>══════════════════════════════════════════════════════════════</>");
        $this->line("<fg=cyan;options=bold>  REPORTE DE OPERACIÓN — {$date}</>");
        $this->line("<fg=cyan;options=bold>══════════════════════════════════════════════════════════════</>");

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['<fg=green>Órdenes POS cerradas</>', '<fg=green;options=bold>'.$stats['orders_ok'].'</>'],
                ['Órdenes canceladas', $stats['orders_cancelled']],
                ['Órdenes vacías (descartadas)', $stats['orders_failed']],
                ['<fg=green>Facturas generadas</>', '<fg=green;options=bold>'.$stats['invoices_ok'].'</>'],
                ['Facturas fallidas', $stats['invoices_failed']],
                ['Items vendidos (POS)', $stats['items_sold']],
            ]
        );

        $this->table(
            ['Caja Registradora', 'Valor (COP)'],
            [
                ['Monto apertura',     '$ '.number_format((float)$cash->opening_amount, 0, ',', '.')],
                ['Total efectivo',     '$ '.number_format((float)$cash->total_cash, 0, ',', '.')],
                ['Total tarjeta',      '$ '.number_format((float)$cash->total_card, 0, ',', '.')],
                ['Total transferencia','$ '.number_format((float)$cash->total_transfer, 0, ',', '.')],
                ['Total QR',           '$ '.number_format((float)$cash->total_qr, 0, ',', '.')],
                ['<fg=green;options=bold>TOTAL VENTAS POS</>', '<fg=green;options=bold>$ '.number_format((float)$cash->total_sales, 0, ',', '.').'</>'],
                ['Monto cierre (efectivo en caja)', '<fg=yellow>$ '.number_format((float)$cash->closing_amount, 0, ',', '.').'</>'],
            ]
        );

        $invSubtotal = (float) Invoice::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->sum('subtotal');
        $invIva = (float) Invoice::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->sum('iva');
        $invTotal = (float) Invoice::whereDate('created_at', $date)
            ->where('status', 'completed')
            ->sum('total');

        $this->table(
            ['Facturación (B2B)', 'Valor (COP)'],
            [
                ['Subtotal',           '$ '.number_format($invSubtotal, 0, ',', '.')],
                ['IVA 19%',            '$ '.number_format($invIva, 0, ',', '.')],
                ['<fg=green;options=bold>Total facturado</>', '<fg=green;options=bold>$ '.number_format($invTotal, 0, ',', '.').'</>'],
            ]
        );

        $grandTotal = (float) $cash->total_sales + $invTotal;
        $this->newLine();
        $this->line("<fg=magenta;options=bold>  GRAN TOTAL DEL DÍA: \$ ".number_format($grandTotal, 0, ',', '.')." COP</>");
        $this->newLine();
    }
}
