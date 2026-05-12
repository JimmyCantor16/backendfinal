<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;

class PlanController extends Controller
{
    /**
     * Lista los planes activos disponibles para suscripción.
     *
     * @OA\Get(
     *     path="/api/plans",
     *     tags={"Plans"},
     *     summary="Lista los planes activos disponibles.",
     *     @OA\Response(response=200, description="Listado de planes")
     * )
     */
    public function index()
    {
        $plans = Plan::active()->orderBy('price_cents')->get();

        return response()->json($plans);
    }

    /**
     * Detalle de un plan.
     *
     * @OA\Get(
     *     path="/api/plans/{id}",
     *     tags={"Plans"},
     *     summary="Detalle de un plan.",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Plan encontrado"),
     *     @OA\Response(response=404, description="Plan no encontrado")
     * )
     */
    public function show($id)
    {
        $plan = Plan::findOrFail($id);

        return response()->json($plan);
    }
}
