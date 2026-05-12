<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * Crear nueva orden abierta.
     *
     * @OA\Post(
     *     path="/api/orders",
     *     tags={"Orders"},
     *     summary="Crea una nueva orden POS abierta para el usuario.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Orden creada"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=409, description="No se pudo crear (regla de negocio: ej. caja sin abrir)"),
     * )
     */
    public function store(Request $request)
    {
        try {
            $order = $this->orderService->createOrder($request->user()->id);

            return response()->json($order->load('user'), 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Listar órdenes abiertas con sus items.
     *
     * @OA\Get(
     *     path="/api/orders/open",
     *     tags={"Orders"},
     *     summary="Lista las órdenes POS abiertas con sus items.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Listado de órdenes abiertas"),
     *     @OA\Response(response=401, description="No autenticado"),
     * )
     */
    public function open()
    {
        $orders = Order::where('status', 'open')
            ->with(['user', 'items.product'])
            ->orderByDesc('opened_at')
            ->get();

        return response()->json($orders);
    }

    /**
     * Agregar item a una orden — descuenta stock.
     *
     * @OA\Post(
     *     path="/api/orders/{order}/items",
     *     tags={"Orders"},
     *     summary="Agrega un item a la orden y descuenta stock del producto.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id","quantity"},
     *             @OA\Property(property="product_id", type="integer", example=10),
     *             @OA\Property(property="quantity", type="integer", minimum=1, example=2),
     *         )
     *     ),
     *     @OA\Response(response=201, description="Item agregado a la orden"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=409, description="Regla de negocio violada (orden cerrada, stock insuficiente, etc.)"),
     *     @OA\Response(response=422, description="Error de validación"),
     * )
     */
    public function addItem(Request $request, Order $order)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id,business_id,' . $request->user()->business_id,
            'quantity' => 'required|integer|min:1',
        ]);

        try {
            $item = $this->orderService->addItem(
                $order,
                $validated['product_id'],
                $validated['quantity'],
                $request->user()->id
            );

            return response()->json($item, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Eliminar item de una orden — devuelve stock.
     *
     * @OA\Delete(
     *     path="/api/orders/{order}/items/{item}",
     *     tags={"Orders"},
     *     summary="Elimina un item de la orden y restaura el stock del producto.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="item", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Item eliminado y stock restaurado"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=409, description="Regla de negocio violada (orden cerrada, item no pertenece a la orden, etc.)"),
     * )
     */
    public function removeItem(Request $request, Order $order, OrderItem $item)
    {
        try {
            $this->orderService->removeItem($order, $item, $request->user()->id);

            return response()->json(['message' => 'Item eliminado y stock restaurado.']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Cerrar orden — requiere método de pago.
     *
     * @OA\Post(
     *     path="/api/orders/{order}/close",
     *     tags={"Orders"},
     *     summary="Cierra la orden indicando método de pago.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_method"},
     *             @OA\Property(property="payment_method", type="string", enum={"cash","card","transfer","qr"}, example="cash")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Orden cerrada"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=409, description="Regla de negocio violada (orden vacía, ya cerrada, sin caja, etc.)"),
     *     @OA\Response(response=422, description="Error de validación"),
     * )
     */
    public function close(Request $request, Order $order)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,card,transfer,qr',
        ]);

        try {
            $order = $this->orderService->closeOrder($order, $validated['payment_method']);

            return response()->json($order->load(['user', 'items.product']));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Cancelar orden — devuelve todo el stock.
     *
     * @OA\Post(
     *     path="/api/orders/{order}/cancel",
     *     tags={"Orders"},
     *     summary="Cancela la orden y restaura el stock de todos sus items.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Orden cancelada"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=409, description="Regla de negocio violada (orden ya cerrada o cancelada)"),
     * )
     */
    public function cancel(Request $request, Order $order)
    {
        try {
            $order = $this->orderService->cancelOrder($order, $request->user()->id);

            return response()->json($order->load(['user', 'items.product']));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }
}
