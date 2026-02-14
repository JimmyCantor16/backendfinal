<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    protected InventoryService $inventoryService;
    protected AuditService $auditService;

    public function __construct(InventoryService $inventoryService, AuditService $auditService)
    {
        $this->inventoryService = $inventoryService;
        $this->auditService = $auditService;
    }

    /**
     * Crear factura: valida stock, descuenta inventario, calcula IVA.
     */
    public function createInvoice(array $data, int $userId): Invoice
    {
        return DB::transaction(function () use ($data, $userId) {
            // Generar número de factura secuencial (scoped por negocio)
            $businessId = auth()->user()->business_id;
            $lastInvoice = Invoice::where('business_id', $businessId)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->first();
            $nextNumber = $lastInvoice ? intval(substr($lastInvoice->invoice_number, 4)) + 1 : 1;
            $invoiceNumber = 'FAC-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            $subtotal = 0;
            $itemsData = [];

            // Validar stock y preparar items
            foreach ($data['items'] as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw new \InvalidArgumentException(
                        "Stock insuficiente para {$product->name}. Disponible: {$product->stock}, solicitado: {$item['quantity']}"
                    );
                }

                $unitPrice = $product->sale_price;
                $itemSubtotal = $item['quantity'] * $unitPrice;
                $subtotal += $itemSubtotal;

                $itemsData[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $ivaRate = 19.00;
            $iva = round($subtotal * ($ivaRate / 100), 2);
            $total = $subtotal + $iva;

            $invoice = Invoice::create([
                'client_id' => $data['client_id'],
                'user_id' => $userId,
                'invoice_number' => $invoiceNumber,
                'subtotal' => $subtotal,
                'iva_rate' => $ivaRate,
                'iva' => $iva,
                'total' => $total,
                'payment_method' => 'cash',
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
            ]);

            // Crear items y descontar stock
            foreach ($itemsData as $itemData) {
                $invoice->items()->create([
                    'product_id' => $itemData['product']->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'subtotal' => $itemData['subtotal'],
                ]);

                $this->inventoryService->decreaseStock(
                    $itemData['product'],
                    $itemData['quantity'],
                    $userId,
                    Invoice::class,
                    $invoice->id,
                    "Venta factura {$invoiceNumber}",
                    'sale_out'
                );
            }

            $this->auditService->log('Invoice', $invoice->id, 'created', null, [
                'invoice_number' => $invoiceNumber,
                'client_id' => $data['client_id'],
                'total' => $total,
                'items_count' => count($data['items']),
            ]);

            return $invoice;
        });
    }

    /**
     * Cancelar factura: revierte stock de cada item.
     */
    public function cancelInvoice(Invoice $invoice, int $userId): Invoice
    {
        if ($invoice->status === 'cancelled') {
            throw new \InvalidArgumentException('La factura ya está cancelada.');
        }

        return DB::transaction(function () use ($invoice, $userId) {
            foreach ($invoice->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);

                $this->inventoryService->increaseStock(
                    $product,
                    $item->quantity,
                    $userId,
                    Invoice::class,
                    $invoice->id,
                    "Cancelación factura {$invoice->invoice_number}",
                    'purchase_in'
                );
            }

            $oldValues = ['status' => $invoice->status, 'total' => $invoice->total];

            $invoice->update(['status' => 'cancelled']);

            $this->auditService->log('Invoice', $invoice->id, 'cancelled', $oldValues, [
                'status' => 'cancelled',
                'invoice_number' => $invoice->invoice_number,
            ]);

            return $invoice->fresh();
        });
    }
}
