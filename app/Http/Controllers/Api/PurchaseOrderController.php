<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Product;
use App\Services\AuditService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    protected InventoryService $inventoryService;
    protected AuditService $auditService;

    public function __construct(InventoryService $inventoryService, AuditService $auditService)
    {
        $this->inventoryService = $inventoryService;
        $this->auditService = $auditService;
    }

    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:pending,received,cancelled',
            'supplier_id' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = PurchaseOrder::with(['supplier', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $perPage = $request->integer('per_page', 50);

        return response()->json($query->orderByDesc('created_at')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id,business_id,' . $request->user()->business_id,
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id,business_id,' . $request->user()->business_id,
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $order = DB::transaction(function () use ($validated, $request) {
            // Generar número de orden secuencial (scoped por negocio)
            $businessId = $request->user()->business_id;
            $lastOrder = PurchaseOrder::where('business_id', $businessId)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();
            $nextNumber = $lastOrder ? intval(substr($lastOrder->order_number, 3)) + 1 : 1;
            $orderNumber = 'OC-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $itemSubtotal = $item['quantity'] * $item['unit_cost'];
                $subtotal += $itemSubtotal;
                $itemsData[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'],
                    'subtotal' => $itemSubtotal,
                ];
            }

            $iva = round($subtotal * 0.19, 2);
            $total = $subtotal + $iva;

            $order = PurchaseOrder::create([
                'supplier_id' => $validated['supplier_id'],
                'user_id' => $request->user()->id,
                'order_number' => $orderNumber,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $total,
                'notes' => $validated['notes'] ?? null,
            ]);

            $order->items()->createMany($itemsData);

            return $order;
        });

        return response()->json($order->load(['supplier', 'user', 'items.product']), 201);
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        return response()->json(
            $purchaseOrder->load(['supplier', 'user', 'items.product'])
        );
    }

    /**
     * Recibir orden de compra — aumenta stock de cada producto.
     */
    public function receive(PurchaseOrder $purchaseOrder, Request $request)
    {
        if ($purchaseOrder->status !== 'pending') {
            return response()->json([
                'message' => 'Solo se pueden recibir órdenes en estado pendiente.'
            ], 409);
        }

        DB::transaction(function () use ($purchaseOrder, $request) {
            foreach ($purchaseOrder->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);

                $this->inventoryService->increaseStock(
                    $product,
                    $item->quantity,
                    $request->user()->id,
                    PurchaseOrder::class,
                    $purchaseOrder->id,
                    "Recepción de orden {$purchaseOrder->order_number}",
                    'purchase_in'
                );
            }

            $purchaseOrder->update([
                'status' => 'received',
                'received_at' => now(),
            ]);

            $this->auditService->log('PurchaseOrder', $purchaseOrder->id, 'received',
                ['status' => 'pending'],
                ['status' => 'received', 'order_number' => $purchaseOrder->order_number, 'total' => $purchaseOrder->total]
            );
        });

        return response()->json(
            $purchaseOrder->fresh()->load(['supplier', 'user', 'items.product'])
        );
    }

    /**
     * Cancelar orden de compra (solo si está pendiente).
     */
    public function cancel(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'pending') {
            return response()->json([
                'message' => 'Solo se pueden cancelar órdenes en estado pendiente.'
            ], 409);
        }

        $purchaseOrder->update(['status' => 'cancelled']);

        $this->auditService->log('PurchaseOrder', $purchaseOrder->id, 'cancelled',
            ['status' => 'pending'],
            ['status' => 'cancelled', 'order_number' => $purchaseOrder->order_number]
        );

        return response()->json(
            $purchaseOrder->load(['supplier', 'user', 'items.product'])
        );
    }
}
