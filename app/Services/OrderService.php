<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderService
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Crear una nueva orden abierta.
     */
    public function createOrder(int $userId): Order
    {
        return DB::transaction(function () use ($userId) {
            $lastOrder = Order::lockForUpdate()->orderByDesc('id')->first();
            $nextNumber = $lastOrder ? intval(substr($lastOrder->order_number, 4)) + 1 : 1;
            $orderNumber = 'ORD-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            return Order::create([
                'user_id' => $userId,
                'order_number' => $orderNumber,
                'status' => 'open',
                'total' => 0,
                'opened_at' => now(),
            ]);
        });
    }

    /**
     * Agregar item a orden abierta — descuenta stock inmediatamente.
     */
    public function addItem(Order $order, int $productId, int $quantity, int $userId): OrderItem
    {
        if ($order->status !== 'open') {
            throw new \InvalidArgumentException('No se pueden agregar items a una orden que no está abierta.');
        }

        return DB::transaction(function () use ($order, $productId, $quantity, $userId) {
            $product = Product::lockForUpdate()->findOrFail($productId);

            if ($product->stock < $quantity) {
                throw new \InvalidArgumentException(
                    "Stock insuficiente para {$product->name}. Disponible: {$product->stock}, solicitado: {$quantity}"
                );
            }

            $unitPrice = $product->sale_price;
            $subtotal = $quantity * $unitPrice;

            $item = $order->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);

            // Descontar stock inmediatamente
            $this->inventoryService->decreaseStock(
                $product,
                $quantity,
                $userId,
                Order::class,
                $order->id,
                "Orden {$order->order_number} - agregar item",
                'order_out'
            );

            $order->recalculateTotal();

            return $item->load('product');
        });
    }

    /**
     * Eliminar item de orden abierta — devuelve stock.
     */
    public function removeItem(Order $order, OrderItem $item, int $userId): void
    {
        if ($order->status !== 'open') {
            throw new \InvalidArgumentException('No se pueden eliminar items de una orden que no está abierta.');
        }

        if ($item->order_id !== $order->id) {
            throw new \InvalidArgumentException('El item no pertenece a esta orden.');
        }

        DB::transaction(function () use ($order, $item, $userId) {
            $product = Product::lockForUpdate()->findOrFail($item->product_id);

            // Devolver stock
            $this->inventoryService->increaseStock(
                $product,
                $item->quantity,
                $userId,
                Order::class,
                $order->id,
                "Orden {$order->order_number} - eliminar item",
                'order_return'
            );

            $item->delete();
            $order->recalculateTotal();
        });
    }

    /**
     * Cerrar orden — requiere método de pago, la orden no puede estar vacía.
     */
    public function closeOrder(Order $order, string $paymentMethod): Order
    {
        if ($order->status !== 'open') {
            throw new \InvalidArgumentException('Solo se pueden cerrar órdenes abiertas.');
        }

        if ($order->items()->count() === 0) {
            throw new \InvalidArgumentException('No se puede cerrar una orden sin items.');
        }

        $order->update([
            'status' => 'closed',
            'payment_method' => $paymentMethod,
            'closed_at' => now(),
        ]);

        return $order->fresh();
    }

    /**
     * Cancelar orden — devuelve stock de todos los items.
     */
    public function cancelOrder(Order $order, int $userId): Order
    {
        if ($order->status !== 'open') {
            throw new \InvalidArgumentException('Solo se pueden cancelar órdenes abiertas.');
        }

        DB::transaction(function () use ($order, $userId) {
            foreach ($order->items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item->product_id);

                $this->inventoryService->increaseStock(
                    $product,
                    $item->quantity,
                    $userId,
                    Order::class,
                    $order->id,
                    "Cancelación orden {$order->order_number}",
                    'order_return'
                );
            }

            $order->update([
                'status' => 'cancelled',
                'closed_at' => now(),
            ]);
        });

        return $order->fresh();
    }
}
