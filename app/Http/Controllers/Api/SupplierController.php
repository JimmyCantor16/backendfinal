<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Supplier::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nit', 'like', "%{$search}%");
            });
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
            'name' => 'required|string|max:255',
            'nit' => 'required|string|max:20|unique:suppliers,nit,NULL,id,business_id,' . $request->user()->business_id,
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $supplier = Supplier::create($validated);

        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier)
    {
        return response()->json($supplier);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nit' => 'required|string|max:20|unique:suppliers,nit,' . $supplier->id . ',id,business_id,' . $request->user()->business_id,
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $supplier->update($validated);

        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchaseOrders()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el proveedor porque tiene órdenes de compra asociadas.'
            ], 409);
        }

        $supplier->delete();

        return response()->json(['message' => 'Proveedor eliminado correctamente.']);
    }
}
