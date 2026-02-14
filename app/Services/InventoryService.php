<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;

class InventoryService
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Aumenta el stock de un producto (compras).
     */
    public function increaseStock(
        Product $product,
        int $quantity,
        int $userId,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        string $type = 'purchase_in'
    ): InventoryMovement {
        $stockBefore = $product->stock;
        $product->increment('stock', $quantity);

        return InventoryMovement::create([
            'product_id' => $product->id,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockBefore + $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
        ]);
    }

    /**
     * Disminuye el stock de un producto (ventas).
     */
    public function decreaseStock(
        Product $product,
        int $quantity,
        int $userId,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $reason = null,
        string $type = 'sale_out'
    ): InventoryMovement {
        if ($product->stock < $quantity) {
            throw new \InvalidArgumentException(
                "Stock insuficiente para {$product->name}. Disponible: {$product->stock}, solicitado: {$quantity}"
            );
        }

        $stockBefore = $product->stock;
        $product->decrement('stock', $quantity);

        return InventoryMovement::create([
            'product_id' => $product->id,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $stockBefore - $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'reason' => $reason,
        ]);
    }

    /**
     * Ajuste manual de inventario.
     */
    public function adjustStock(
        Product $product,
        int $newStock,
        int $userId,
        string $reason
    ): InventoryMovement {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($product, $newStock, $userId, $reason) {
            $product = Product::lockForUpdate()->findOrFail($product->id);

            $stockBefore = $product->stock;
            $quantity = $newStock - $stockBefore;

            $product->update(['stock' => $newStock]);

            $movement = InventoryMovement::create([
                'product_id' => $product->id,
                'user_id' => $userId,
                'type' => 'adjustment',
                'quantity' => $quantity,
                'stock_before' => $stockBefore,
                'stock_after' => $newStock,
                'reason' => $reason,
            ]);

            $this->auditService->log('Product', $product->id, 'stock_adjusted', [
                'stock' => $stockBefore,
            ], [
                'stock' => $newStock,
                'reason' => $reason,
            ]);

            return $movement;
        });
    }
}
