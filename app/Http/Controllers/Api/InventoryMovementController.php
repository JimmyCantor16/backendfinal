<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryMovementController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public function index(Request $request)
    {
        $query = InventoryMovement::with(['product', 'user']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    /**
     * Ajuste manual de inventario.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'new_stock' => 'required|integer|min:0',
            'reason' => 'required|string|max:255',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        $movement = $this->inventoryService->adjustStock(
            $product,
            $validated['new_stock'],
            $request->user()->id,
            $validated['reason']
        );

        return response()->json($movement->load(['product', 'user']), 201);
    }
}
