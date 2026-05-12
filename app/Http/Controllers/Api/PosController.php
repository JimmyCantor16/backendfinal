<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class PosController extends Controller
{
    /**
     * Endpoint agregado: devuelve TODO lo que la pantalla POS necesita al
     * cargar (productos activos, categorías, órdenes abiertas y caja actual).
     *
     * Razón: en lugar de 4 round-trips serializados al backend, hacemos uno
     * solo dentro de la misma request — comparte el boot de Laravel, la
     * conexión a DB y la autenticación Sanctum. Reduce el TTI de /pos de ~20 s
     * a ~1 s sin necesidad de Octane.
     *
     * @OA\Get(
     *     path="/api/pos/init",
     *     tags={"POS"},
     *     summary="Carga inicial agregada del POS (productos, categorías, órdenes abiertas, caja).",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Datos iniciales del POS",
     *         @OA\JsonContent(
     *             @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="categories", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="open_orders", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="cash_register", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     * )
     */
    public function init()
    {
        $userId = Auth::id();

        // Una sola sesión, una sola conexión DB, una sola serialización JSON.
        $products = Product::where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = Category::orderBy('name')->get();

        $openOrders = Order::where('status', 'open')
            ->with('items.product:id,name,sale_price')
            ->orderByDesc('opened_at')
            ->get();

        $cashRegister = CashRegister::where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        return response()->json([
            'products' => $products,
            'categories' => $categories,
            'open_orders' => $openOrders,
            'cash_register' => $cashRegister,
        ]);
    }
}
