<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BusinessSettingsController extends Controller
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Obtener configuración del negocio actual.
     */
    public function show(Request $request)
    {
        $business = $request->user()->business;

        $data = $business->toArray();
        $data['logo_url'] = $business->logo
            ? Storage::url($business->logo)
            : null;

        return response()->json($data);
    }

    /**
     * Actualizar configuración del negocio.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'nit' => 'sometimes|string|max:50',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $business = $request->user()->business;
        $oldValues = $business->only(['name', 'nit', 'address', 'phone', 'email']);

        // Upload logo
        if ($request->hasFile('logo')) {
            // Eliminar logo anterior
            if ($business->logo) {
                Storage::disk('public')->delete($business->logo);
            }

            $validated['logo'] = $request->file('logo')->store('logos', 'public');
        }

        $business->update($validated);

        $this->auditService->log('Business', $business->id, 'settings_updated', $oldValues,
            $business->only(['name', 'nit', 'address', 'phone', 'email'])
        );

        $data = $business->fresh()->toArray();
        $data['logo_url'] = $business->logo
            ? Storage::url($business->logo)
            : null;

        return response()->json($data);
    }
}
