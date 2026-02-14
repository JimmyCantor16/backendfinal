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
     */
    public function report(CashRegister $cashRegister)
    {
        $report = $this->cashRegisterService->report($cashRegister);

        return response()->json($report);
    }
}
