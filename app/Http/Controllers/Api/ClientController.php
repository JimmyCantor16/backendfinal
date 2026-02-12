<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_type' => 'required|in:CC,NIT,CE,TI,PP',
            'document_number' => 'required|string|max:20|unique:clients',
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
            'document_number' => 'required|string|max:20|unique:clients,document_number,' . $client->id,
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
