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
     */
    public function store(Request $request)
    {
        $order = $this->orderService->createOrder($request->user()->id);

        return response()->json($order->load('user'), 201);
    }

    /**
     * Listar órdenes abiertas con sus items.
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
     */
    public function addItem(Request $request, Order $order)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
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
