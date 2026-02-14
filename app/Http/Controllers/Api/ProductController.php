<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer',
            'low_stock' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Product::with('category');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->integer('per_page', 50);

        return response()->json($query->orderBy('name')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id,business_id,' . $request->user()->business_id,
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:50|unique:products,sku,NULL,id,business_id,' . $request->user()->business_id,
            'description' => 'nullable|string|max:255',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'stock' => 'integer|min:0',
            'min_stock' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $product = Product::create($validated);

        $this->auditService->log('Product', $product->id, 'created', null, [
            'name' => $product->name,
            'sku' => $product->sku,
            'sale_price' => $product->sale_price,
        ]);

        return response()->json($product->load('category'), 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load('category'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id,business_id,' . $request->user()->business_id,
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:50|unique:products,sku,' . $product->id . ',id,business_id,' . $request->user()->business_id,
            'description' => 'nullable|string|max:255',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'min_stock' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $oldValues = $product->only(['name', 'sku', 'sale_price', 'purchase_price', 'is_active']);

        $product->update($validated);

        $this->auditService->log('Product', $product->id, 'updated', $oldValues,
            $product->only(['name', 'sku', 'sale_price', 'purchase_price', 'is_active'])
        );

        return response()->json($product->load('category'));
    }

    public function destroy(Product $product)
    {
        if ($product->stock > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un producto con stock disponible.'
            ], 409);
        }

        $this->auditService->log('Product', $product->id, 'deleted', [
            'name' => $product->name,
            'sku' => $product->sku,
        ], null);

        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente.']);
    }
}
