<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;

class EnsureBusinessContext
{
    /**
     * Lee el header X-Business-Id, valida que el usuario autenticado pertenezca
     * a ese negocio (ya sea como owner, business asignado o business activo) e
     * inyecta business_id en los atributos del request.
     *
     * Falla con:
     *  - 401 si no hay usuario autenticado.
     *  - 400 si el header está ausente o no es válido.
     *  - 403 si el usuario no pertenece al negocio.
     *  - 404 si el negocio no existe.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $businessId = $request->header('X-Business-Id');

        if (!$businessId || !ctype_digit((string) $businessId)) {
            return response()->json(['message' => 'Header X-Business-Id requerido'], 400);
        }

        $businessId = (int) $businessId;

        $business = Business::find($businessId);
        if (!$business) {
            return response()->json(['message' => 'Negocio no encontrado'], 404);
        }

        $belongs = $business->owner_user_id === $user->id
            || $user->business_id === $business->id
            || $user->current_business_id === $business->id;

        if (!$belongs) {
            return response()->json(['message' => 'No autorizado para este negocio'], 403);
        }

        $request->attributes->set('business_id', $businessId);

        return $next($request);
    }
}
