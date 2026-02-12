<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Product;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function summary()
    {
        $today = Carbon::today();

        $salesToday = Invoice::where('status', 'completed')
            ->whereDate('created_at', $today)
            ->sum('total');

        $salesMonth = Invoice::where('status', 'completed')
            ->whereMonth('created_at', $today->month)
            ->whereYear('created_at', $today->year)
            ->sum('total');

        $invoicesToday = Invoice::whereDate('created_at', $today)->count();

        $lowStockProducts = Product::where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->with('category')
            ->get();

        return response()->json([
            'ventas_hoy' => round($salesToday, 2),
            'ventas_mes' => round($salesMonth, 2),
            'facturas_hoy' => $invoicesToday,
            'productos_stock_bajo' => $lowStockProducts,
        ]);
    }
}
