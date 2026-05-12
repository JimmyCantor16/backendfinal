<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * @OA\Get(
     *     path="/api/invoices",
     *     tags={"Invoices"},
     *     summary="Lista paginada de facturas con filtros opcionales.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string", enum={"completed","cancelled"})),
     *     @OA\Parameter(name="client_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date_from", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", minimum=1, maximum=100, default=50)),
     *     @OA\Response(response=200, description="Listado paginado de facturas"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=422, description="Parámetros inválidos"),
     * )
     */
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:completed,cancelled',
            'client_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Invoice::with(['client', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->integer('per_page', 50);

        return response()->json($query->orderByDesc('created_at')->paginate($perPage));
    }

    /**
     * @OA\Post(
     *     path="/api/invoices",
     *     tags={"Invoices"},
     *     summary="Crea una factura para un cliente con uno o más ítems.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"client_id","items"},
     *             @OA\Property(property="client_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(
     *                     required={"product_id","quantity"},
     *                     @OA\Property(property="product_id", type="integer", example=10),
     *                     @OA\Property(property="quantity", type="integer", minimum=1, example=2)
     *                 )
     *             ),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Factura creada"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=409, description="Regla de negocio violada (stock insuficiente, etc.)"),
     *     @OA\Response(response=422, description="Error de validación"),
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id,business_id,' . $request->user()->business_id,
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id,business_id,' . $request->user()->business_id,
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        try {
            $invoice = $this->invoiceService->createInvoice($validated, $request->user()->id);

            return response()->json(
                $invoice->load(['client', 'user', 'items.product']),
                201
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/invoices/{invoice}",
     *     tags={"Invoices"},
     *     summary="Devuelve el detalle de una factura.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="invoice", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Factura encontrada"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Factura no encontrada"),
     * )
     */
    public function show(Invoice $invoice)
    {
        return response()->json(
            $invoice->load(['client', 'user', 'items.product'])
        );
    }

    /**
     * @OA\Post(
     *     path="/api/invoices/{invoice}/cancel",
     *     tags={"Invoices"},
     *     summary="Cancela una factura y restaura el stock.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="invoice", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Factura cancelada"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Factura no encontrada"),
     *     @OA\Response(response=409, description="Factura ya estaba cancelada u otra regla de negocio"),
     * )
     */
    public function cancel(Invoice $invoice, Request $request)
    {
        try {
            $invoice = $this->invoiceService->cancelInvoice($invoice, $request->user()->id);

            return response()->json(
                $invoice->load(['client', 'user', 'items.product'])
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }
}
