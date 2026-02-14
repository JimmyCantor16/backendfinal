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

    public function show(Invoice $invoice)
    {
        return response()->json(
            $invoice->load(['client', 'user', 'items.product'])
        );
    }

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
