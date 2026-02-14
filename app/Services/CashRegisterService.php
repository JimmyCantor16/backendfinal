<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class CashRegisterService
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Abrir una nueva caja registradora para el usuario actual.
     */
    public function open(int $userId, float $openingAmount): CashRegister
    {
        return DB::transaction(function () use ($userId, $openingAmount) {
            // Lock para prevenir apertura concurrente de dos cajas
            $existingOpen = CashRegister::where('user_id', $userId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existingOpen) {
                throw new \InvalidArgumentException('Ya tienes una caja registradora abierta. Ciérrala antes de abrir otra.');
            }

            $cashRegister = CashRegister::create([
                'user_id' => $userId,
                'opening_amount' => $openingAmount,
                'total_cash' => 0,
                'total_card' => 0,
                'total_transfer' => 0,
                'total_qr' => 0,
                'total_sales' => 0,
                'status' => 'open',
                'opened_at' => now(),
            ]);

            $this->auditService->log('CashRegister', $cashRegister->id, 'opened', null, [
                'opening_amount' => $openingAmount,
            ]);

            return $cashRegister;
        });
    }

    /**
     * Cerrar caja registradora — calcula totales por método de pago.
     */
    public function close(CashRegister $cashRegister): CashRegister
    {
        if ($cashRegister->status !== 'open') {
            throw new \InvalidArgumentException('Esta caja ya está cerrada.');
        }

        return DB::transaction(function () use ($cashRegister) {
            $cashRegister = CashRegister::lockForUpdate()->find($cashRegister->id);

            // Calcular totales por método de pago desde órdenes cerradas
            $orders = $cashRegister->orders()
                ->where('status', 'closed')
                ->get();

            $totalCash = $orders->where('payment_method', 'cash')->sum('total');
            $totalCard = $orders->where('payment_method', 'card')->sum('total');
            $totalTransfer = $orders->where('payment_method', 'transfer')->sum('total');
            $totalQr = $orders->where('payment_method', 'qr')->sum('total');
            $totalSales = $totalCash + $totalCard + $totalTransfer + $totalQr;
            $closingAmount = $cashRegister->opening_amount + $totalCash;

            $oldValues = ['status' => 'open', 'opening_amount' => $cashRegister->opening_amount];

            $cashRegister->update([
                'total_cash' => $totalCash,
                'total_card' => $totalCard,
                'total_transfer' => $totalTransfer,
                'total_qr' => $totalQr,
                'total_sales' => $totalSales,
                'closing_amount' => $closingAmount,
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            $this->auditService->log('CashRegister', $cashRegister->id, 'closed', $oldValues, [
                'total_sales' => $totalSales,
                'total_cash' => $totalCash,
                'total_card' => $totalCard,
                'total_transfer' => $totalTransfer,
                'total_qr' => $totalQr,
                'closing_amount' => $closingAmount,
            ]);

            return $cashRegister->fresh();
        });
    }

    /**
     * Obtener la caja abierta del usuario actual.
     */
    public function current(int $userId): ?CashRegister
    {
        return CashRegister::where('user_id', $userId)
            ->where('status', 'open')
            ->first();
    }

    /**
     * Generar reporte de una caja registradora.
     */
    public function report(CashRegister $cashRegister): array
    {
        $orders = $cashRegister->orders()->get();

        return [
            'cash_register' => $cashRegister->load('user'),
            'resumen' => [
                'total_ordenes' => $orders->count(),
                'ordenes_cerradas' => $orders->where('status', 'closed')->count(),
                'ordenes_canceladas' => $orders->where('status', 'cancelled')->count(),
                'total_cash' => $cashRegister->total_cash,
                'total_card' => $cashRegister->total_card,
                'total_transfer' => $cashRegister->total_transfer,
                'total_qr' => $cashRegister->total_qr,
                'total_ventas' => $cashRegister->total_sales,
                'monto_apertura' => $cashRegister->opening_amount,
                'monto_cierre' => $cashRegister->closing_amount,
            ],
            'ordenes' => $orders->load('items.product'),
        ];
    }
}
