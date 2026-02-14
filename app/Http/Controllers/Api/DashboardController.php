<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function summary()
    {
        $today = Carbon::today();

        // Ventas por facturas
        $invoiceSalesToday = Invoice::where('status', 'completed')
            ->whereDate('created_at', $today)
            ->sum('total');

        $invoiceSalesMonth = Invoice::where('status', 'completed')
            ->whereMonth('created_at', $today->month)
            ->whereYear('created_at', $today->year)
            ->sum('total');

        // Ventas por órdenes POS cerradas
        $orderSalesToday = Order::where('status', 'closed')
            ->whereDate('updated_at', $today)
            ->sum('total');

        $orderSalesMonth = Order::where('status', 'closed')
            ->whereMonth('updated_at', $today->month)
            ->whereYear('updated_at', $today->year)
            ->sum('total');

        $invoicesToday = Invoice::whereDate('created_at', $today)->count();

        $ordersTodayClosed = Order::where('status', 'closed')
            ->whereDate('updated_at', $today)
            ->count();

        $lowStockProducts = Product::where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->with('category')
            ->get();

        return response()->json([
            'ventas_hoy' => round($invoiceSalesToday + $orderSalesToday, 2),
            'ventas_mes' => round($invoiceSalesMonth + $orderSalesMonth, 2),
            'facturas_hoy' => $invoicesToday,
            'ordenes_pos_hoy' => $ordersTodayClosed,
            'productos_stock_bajo' => $lowStockProducts,
        ]);
    }
}
