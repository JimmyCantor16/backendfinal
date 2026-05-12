<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Services\CashRegisterService;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    protected CashRegisterService $cashRegisterService;

    public function __construct(CashRegisterService $cashRegisterService)
    {
        $this->cashRegisterService = $cashRegisterService;
    }

    /**
     * Abrir caja registradora.
     *
     * @OA\Post(
     *     path="/api/cash-register/open",
     *     tags={"CashRegister"},
     *     summary="Abre la caja registradora del usuario con monto inicial.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"opening_amount"},
     *             @OA\Property(property="opening_amount", type="number", format="float", minimum=0, example=100.00)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Caja abierta"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=409, description="Ya existe una caja abierta para este usuario"),
     *     @OA\Response(response=422, description="Error de validación"),
     * )
     */
    public function open(Request $request)
    {
        $validated = $request->validate([
            'opening_amount' => 'required|numeric|min:0',
        ]);

        try {
            $cashRegister = $this->cashRegisterService->open(
                $request->user()->id,
                $validated['opening_amount']
            );

            return response()->json($cashRegister->load('user'), 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Cerrar caja registradora.
     *
     * @OA\Post(
     *     path="/api/cash-register/{cashRegister}/close",
     *     tags={"CashRegister"},
     *     summary="Cierra una caja registradora abierta.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="cashRegister", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Caja cerrada"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Caja no encontrada"),
     *     @OA\Response(response=409, description="Caja ya cerrada u otra regla de negocio"),
     * )
     */
    public function close(CashRegister $cashRegister)
    {
        try {
            $cashRegister = $this->cashRegisterService->close($cashRegister);

            return response()->json($cashRegister->load('user'));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    /**
     * Obtener caja actual del usuario.
     *
     * @OA\Get(
     *     path="/api/cash-register/current",
     *     tags={"CashRegister"},
     *     summary="Devuelve la caja registradora abierta del usuario autenticado (si existe).",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Caja registradora actual"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="El usuario no tiene una caja abierta"),
     * )
     */
    public function current(Request $request)
    {
        $cashRegister = $this->cashRegisterService->current($request->user()->id);

        if (!$cashRegister) {
            return response()->json(['message' => 'No tienes una caja abierta.'], 404);
        }

        $cashRegister->load('user');
        $cashRegister->orders_closed = $cashRegister->orders()->where('status', 'closed')->count();

        return response()->json($cashRegister);
    }

    /**
     * Reporte de una caja registradora.
     *
     * @OA\Get(
     *     path="/api/cash-register/{cashRegister}/report",
     *     tags={"CashRegister"},
     *     summary="Devuelve el reporte (totales por método de pago, ventas, etc.) de una caja.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="cashRegister", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Reporte de caja"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=404, description="Caja no encontrada"),
     * )
     */
    public function report(CashRegister $cashRegister)
    {
        $report = $this->cashRegisterService->report($cashRegister);

        return response()->json($report);
    }
}
