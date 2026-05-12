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

    /**
     * @OA\Get(
     *     path="/api/products",
     *     tags={"Products"},
     *     summary="Lista paginada de productos con filtros opcionales.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string", maxLength=100)),
     *     @OA\Parameter(name="category_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="low_stock", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="is_active", in="query", required=false, @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100, default=50)),
     *     @OA\Response(response=200, description="Listado paginado de productos"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=422, description="Parámetros inválidos"),
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/products",
     *     tags={"Products"},
     *     summary="Crea un producto en el negocio activo.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"category_id","name","sku","purchase_price","sale_price"},
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", maxLength=255, example="Coca-Cola 350ml"),
     *             @OA\Property(property="sku", type="string", maxLength=50, example="SKU-0001"),
     *             @OA\Property(property="description", type="string", maxLength=255, nullable=true),
     *             @OA\Property(property="purchase_price", type="number", format="float", example=2.5),
     *             @OA\Property(property="sale_price", type="number", format="float", example=4),
     *             @OA\Property(property="stock", type="integer", example=100),
     *             @OA\Property(property="min_stock", type="integer", example=10),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *         )
     *     ),
     *     @OA\Response(response=201, description="Producto creado"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=422, description="Error de validación"),
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/products/{product}",
     *     tags={"Products"},
     *     summary="Devuelve el detalle de un producto.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="product", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Producto encontrado"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Producto no encontrado"),
     * )
     */
    public function show(Product $product)
    {
        return response()->json($product->load('category'));
    }

    /**
     * @OA\Put(
     *     path="/api/products/{product}",
     *     tags={"Products"},
     *     summary="Actualiza un producto existente.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="product", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"category_id","name","sku","purchase_price","sale_price"},
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="sku", type="string", maxLength=50),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="purchase_price", type="number", format="float"),
     *             @OA\Property(property="sale_price", type="number", format="float"),
     *             @OA\Property(property="min_stock", type="integer"),
     *             @OA\Property(property="is_active", type="boolean"),
     *         )
     *     ),
     *     @OA\Response(response=200, description="Producto actualizado"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Producto no encontrado"),
     *     @OA\Response(response=422, description="Error de validación"),
     * )
     */
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

    /**
     * @OA\Delete(
     *     path="/api/products/{product}",
     *     tags={"Products"},
     *     summary="Elimina un producto (solo si no tiene stock disponible).",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="product", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Producto eliminado"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Producto no encontrado"),
     *     @OA\Response(response=409, description="Producto tiene stock — no se puede eliminar"),
     * )
     */
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
