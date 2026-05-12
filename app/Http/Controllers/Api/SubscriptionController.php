<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    /**
     * Resuelve el Business activo del usuario autenticado a partir de
     * users.current_business_id (cae a users.business_id como fallback).
     */
    protected function currentBusiness(): ?Business
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        $id = $user->current_business_id ?: $user->business_id;
        if (!$id) {
            return null;
        }

        return Business::find($id);
    }

    /**
     * Devuelve la suscripción actual ('default') del business activo.
     *
     * @OA\Get(
     *     path="/api/subscriptions/current",
     *     tags={"Subscriptions"},
     *     summary="Suscripción actual del negocio activo.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Suscripción actual"),
     *     @OA\Response(response=400, description="Sin negocio activo"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function current()
    {
        $business = $this->currentBusiness();
        if (!$business) {
            return response()->json(['message' => 'Sin negocio activo'], 400);
        }

        $subscription = $business->subscription('default');

        return response()->json([
            'business_id'  => $business->id,
            'subscribed'   => (bool) ($subscription && $subscription->valid()),
            'on_trial'     => $business->onTrial(),
            'on_grace_period' => $subscription ? $subscription->onGracePeriod() : false,
            'cancelled'    => $subscription ? $subscription->canceled() : false,
            'subscription' => $subscription,
        ]);
    }

    /**
     * Crea una sesión de Stripe Checkout para el plan recibido.
     *
     * @OA\Post(
     *     path="/api/subscriptions/checkout",
     *     tags={"Subscriptions"},
     *     summary="Crea una Stripe Checkout Session para suscribirse a un plan.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"plan_id"},
     *             @OA\Property(property="plan_id", type="integer", example=2),
     *             @OA\Property(property="success_url", type="string", nullable=true),
     *             @OA\Property(property="cancel_url", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="URL de Stripe Checkout"),
     *     @OA\Response(response=400, description="Sin negocio activo o plan sin price"),
     *     @OA\Response(response=404, description="Plan no encontrado"),
     *     @OA\Response(response=422, description="Validación")
     * )
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'plan_id'     => 'required|integer|exists:plans,id',
            'success_url' => 'nullable|url',
            'cancel_url'  => 'nullable|url',
        ]);

        $business = $this->currentBusiness();
        if (!$business) {
            return response()->json(['message' => 'Sin negocio activo'], 400);
        }

        $plan = Plan::findOrFail($validated['plan_id']);

        if (empty($plan->stripe_price_id)) {
            return response()->json([
                'message' => "El plan '{$plan->slug}' no tiene stripe_price_id configurado. "
                           . "Configúralo en Stripe Dashboard y actualiza el plan.",
            ], 400);
        }

        $successUrl = $validated['success_url'] ?? url('/billing/success');
        $cancelUrl  = $validated['cancel_url']  ?? url('/billing/cancel');

        $checkout = $business
            ->newSubscription('default', $plan->stripe_price_id)
            ->checkout([
                'success_url' => $successUrl,
                'cancel_url'  => $cancelUrl,
            ]);

        return response()->json([
            'url'        => $checkout->url,
            'session_id' => $checkout->id ?? null,
        ]);
    }

    /**
     * Cancela la suscripción default al final del período (grace).
     *
     * @OA\Post(
     *     path="/api/subscriptions/cancel",
     *     tags={"Subscriptions"},
     *     summary="Cancela la suscripción al final del período actual.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Cancelada"),
     *     @OA\Response(response=400, description="Sin suscripción activa"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function cancel()
    {
        $business = $this->currentBusiness();
        if (!$business) {
            return response()->json(['message' => 'Sin negocio activo'], 400);
        }

        $subscription = $business->subscription('default');
        if (!$subscription) {
            return response()->json(['message' => 'No hay suscripción activa'], 400);
        }

        $subscription->cancel();

        return response()->json([
            'message'      => 'Suscripción cancelada (en grace period)',
            'subscription' => $subscription->fresh(),
        ]);
    }

    /**
     * Reactiva una suscripción que está en grace period (canceled, no expirada).
     *
     * @OA\Post(
     *     path="/api/subscriptions/resume",
     *     tags={"Subscriptions"},
     *     summary="Reactiva una suscripción en grace period.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Reanudada"),
     *     @OA\Response(response=400, description="Sin suscripción o no en grace"),
     *     @OA\Response(response=401, description="No autenticado")
     * )
     */
    public function resume()
    {
        $business = $this->currentBusiness();
        if (!$business) {
            return response()->json(['message' => 'Sin negocio activo'], 400);
        }

        $subscription = $business->subscription('default');
        if (!$subscription) {
            return response()->json(['message' => 'No hay suscripción activa'], 400);
        }

        if (!$subscription->onGracePeriod()) {
            return response()->json([
                'message' => 'La suscripción no está en grace period; no se puede reanudar',
            ], 400);
        }

        $subscription->resume();

        return response()->json([
            'message'      => 'Suscripción reanudada',
            'subscription' => $subscription->fresh(),
        ]);
    }
}
