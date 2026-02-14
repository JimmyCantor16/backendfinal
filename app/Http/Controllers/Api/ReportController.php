<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function daily(Request $request)
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

        // Facturas del día
        $invoices = Invoice::where('status', 'completed')
            ->whereDate('created_at', $date)
            ->get();

        $invoicesSales = $invoices->sum('total');

        // Cajas del día
        $cashRegisters = CashRegister::whereDate('opened_at', $date)
            ->with('user:id,name')
            ->get();

        // Productos con stock bajo
        $lowStock = Product::where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->count();

        return response()->json([
            'fecha' => $date->toDateString(),
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
            'cajas' => $cashRegisters,
            'productos_stock_bajo' => $lowStock,
        ]);
    }
}
