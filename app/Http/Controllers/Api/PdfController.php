<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\CashRegisterService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Controlador de generación de PDFs.
 *
 * - Renderiza Blade templates en `resources/views/pdf/` usando dompdf.
 * - Todas las rutas requieren auth:sanctum (registradas en routes/api.php).
 * - Respeta policies cuando aplica (InvoicePolicy, CashRegisterPolicy).
 */
class PdfController extends Controller
{
    protected CashRegisterService $cashRegisterService;

    public function __construct(CashRegisterService $cashRegisterService)
    {
        $this->cashRegisterService = $cashRegisterService;
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{invoice}/pdf",
     *     tags={"PDFs"},
     *     summary="Genera y descarga el PDF de una factura individual.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="invoice", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="PDF generado correctamente",
     *         @OA\MediaType(mediaType="application/pdf")
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=403, description="Sin permisos para ver la factura"),
     *     @OA\Response(response=404, description="Factura no encontrada"),
     * )
     */
    public function invoice($invoiceId)
    {
        $invoice = Invoice::with(['items.product', 'client', 'user', 'business'])
            ->findOrFail($invoiceId);

        // Policy view de InvoicePolicy
        if (class_exists(\App\Policies\InvoicePolicy::class)
            && method_exists(\App\Policies\InvoicePolicy::class, 'view')
            && auth()->check()) {
            $this->authorize('view', $invoice);
        }

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
        ])->setPaper('letter');

        $filename = 'FAC-' . ($invoice->invoice_number ?: $invoice->id) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * @OA\Get(
     *     path="/api/cash-registers/{cash_register}/report.pdf",
     *     tags={"PDFs"},
     *     summary="Genera el PDF de cierre de caja con totales y desglose.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="cash_register", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="PDF generado correctamente",
     *         @OA\MediaType(mediaType="application/pdf")
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=403, description="Sin permisos para ver la caja"),
     *     @OA\Response(response=404, description="Caja no encontrada"),
     * )
     */
    public function cashRegisterReport($cashRegisterId)
    {
        $cashRegister = CashRegister::findOrFail($cashRegisterId);

        // Policy view de CashRegisterPolicy si existe
        if (class_exists(\App\Policies\CashRegisterPolicy::class)
            && method_exists(\App\Policies\CashRegisterPolicy::class, 'view')
            && auth()->check()) {
            $this->authorize('view', $cashRegister);
        }

        $report = $this->cashRegisterService->report($cashRegister);

        // Reagrupar para el template
        $orders = $report['ordenes'];
        $closedOrders = $orders->where('status', 'closed')->values();
        $cancelledOrders = $orders->where('status', 'cancelled')->values();

        $data = [
            'cash_register' => $report['cash_register'],
            'resumen' => $report['resumen'],
            'closed_orders' => $closedOrders,
            'cancelled_orders' => $cancelledOrders,
            'business' => $cashRegister->business,
        ];

        $pdf = Pdf::loadView('pdf.cash-register-report', $data)->setPaper('letter');

        $fecha = optional($cashRegister->opened_at)->format('Y-m-d') ?: now()->format('Y-m-d');
        $filename = "CIERRE-CAJA-{$cashRegister->id}-{$fecha}.pdf";

        return $pdf->download($filename);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/daily.pdf",
     *     tags={"PDFs"},
     *     summary="Genera el PDF del reporte diario (ventas, métodos, top productos).",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         required=false,
     *         description="Fecha en formato YYYY-MM-DD. Default: hoy.",
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PDF generado correctamente",
     *         @OA\MediaType(mediaType="application/pdf")
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=422, description="Fecha inválida"),
     * )
     */
    public function dailyReport(Request $request)
    {
        $request->validate([
            'date' => 'nullable|date',
        ]);

        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : Carbon::today();

        // Órdenes POS cerradas del día
        $closedOrders = Order::where('status', 'closed')
            ->whereDate('closed_at', $date)
            ->get();

        $ordersSales = $closedOrders->sum('total');
        $ordersCash = $closedOrders->where('payment_method', 'cash')->sum('total');
        $ordersCard = $closedOrders->where('payment_method', 'card')->sum('total');
        $ordersTransfer = $closedOrders->where('payment_method', 'transfer')->sum('total');
        $ordersQr = $closedOrders->where('payment_method', 'qr')->sum('total');

        // Facturas del día (status completed)
        $invoices = Invoice::where('status', 'completed')
            ->whereDate('created_at', $date)
            ->get();

        $invoicesSales = $invoices->sum('total');

        // Top 5 productos vendidos (suma de quantity desde order_items + invoice_items)
        // Construimos consulta tenant-aware (auth scope global filtra por business_id en parents)
        $orderIds = $closedOrders->pluck('id');
        $invoiceIds = $invoices->pluck('id');

        $topFromOrders = OrderItem::whereIn('order_id', $orderIds)
            ->select('product_id', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(subtotal) as total'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id')
            ->toArray();

        $topFromInvoices = InvoiceItem::whereIn('invoice_id', $invoiceIds)
            ->select('product_id', DB::raw('SUM(quantity) as qty'), DB::raw('SUM(subtotal) as total'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id')
            ->toArray();

        // Sumar quantities por producto
        $combined = [];
        foreach ($topFromOrders as $pid => $qty) {
            $combined[$pid] = ($combined[$pid] ?? 0) + (int) $qty;
        }
        foreach ($topFromInvoices as $pid => $qty) {
            $combined[$pid] = ($combined[$pid] ?? 0) + (int) $qty;
        }

        arsort($combined);
        $topProductIds = array_slice(array_keys($combined), 0, 5, true);

        $products = Product::whereIn('id', $topProductIds)->get()->keyBy('id');

        $topProducts = [];
        foreach ($topProductIds as $pid) {
            if (isset($products[$pid])) {
                $topProducts[] = [
                    'product' => $products[$pid],
                    'quantity' => $combined[$pid],
                ];
            }
        }

        $business = auth()->check() && auth()->user()->business_id
            ? \App\Models\Business::find(auth()->user()->business_id)
            : null;

        $data = [
            'date' => $date,
            'business' => $business,
            'ventas_totales' => round($ordersSales + $invoicesSales, 2),
            'ordenes_pos' => [
                'total' => $closedOrders->count(),
                'ventas' => round($ordersSales, 2),
                'efectivo' => round($ordersCash, 2),
                'tarjeta' => round($ordersCard, 2),
                'transferencia' => round($ordersTransfer, 2),
                'qr' => round($ordersQr, 2),
            ],
            'facturas' => [
                'total' => $invoices->count(),
                'ventas' => round($invoicesSales, 2),
            ],
            'top_products' => $topProducts,
            'generated_at' => now(),
        ];

        $pdf = Pdf::loadView('pdf.daily-report', $data)->setPaper('letter');

        $filename = 'REPORTE-DIARIO-' . $date->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }
}
