<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Client::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 50);

        return response()->json($query->orderBy('name')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_type' => 'required|in:CC,NIT,CE,TI,PP',
            'document_number' => 'required|string|max:20|unique:clients,document_number,NULL,id,business_id,' . $request->user()->business_id,
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
        ]);

        $client = Client::create($validated);

        return response()->json($client, 201);
    }

    public function show(Client $client)
    {
        return response()->json($client);
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'document_type' => 'required|in:CC,NIT,CE,TI,PP',
            'document_number' => 'required|string|max:20|unique:clients,document_number,' . $client->id . ',id,business_id,' . $request->user()->business_id,
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
        ]);

        $client->update($validated);

        return response()->json($client);
    }

    public function destroy(Client $client)
    {
        if ($client->invoices()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el cliente porque tiene facturas asociadas.'
            ], 409);
        }

        $client->delete();

        return response()->json(['message' => 'Cliente eliminado correctamente.']);
    }
}
