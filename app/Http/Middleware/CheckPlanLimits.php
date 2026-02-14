<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimits
{
    /**
     * Verificar que la suscripción del negocio está activa y los límites del plan no se han excedido.
     */
    public function handle(Request $request, Closure $next, ?string $resource = null): Response
    {
        $user = $request->user();

        if (!$user || !$user->loadMissing('business')->business) {
            return response()->json([
                'message' => 'No se encontró un negocio asociado al usuario.'
            ], 403);
        }

        $business = $user->business;

        // Verificar suscripción activa
        if ($business->subscription_status !== 'active') {
            return response()->json([
                'message' => 'La suscripción del negocio está ' . $business->subscription_status . '. Contacta al administrador.'
            ], 403);
        }

        // Verificar límites por recurso (preparado para implementación futura)
        if ($resource && $business->plan_limits) {
            $limits = $business->plan_limits;
            $maxKey = "max_{$resource}";

            if (isset($limits[$maxKey]) && $limits[$maxKey] !== null) {
                $currentCount = $this->getResourceCount($business, $resource);

                if ($currentCount >= $limits[$maxKey]) {
                    return response()->json([
                        'message' => "Has alcanzado el límite de {$limits[$maxKey]} {$resource} en tu plan {$business->subscription_plan}."
                    ], 403);
                }
            }
        }

        return $next($request);
    }

    /**
     * Obtener el conteo actual de un recurso para el negocio.
     */
    protected function getResourceCount($business, string $resource): int
    {
        return match ($resource) {
            'products' => $business->products()->count(),
            'users' => $business->users()->count(),
            default => 0,
        };
    }
}
