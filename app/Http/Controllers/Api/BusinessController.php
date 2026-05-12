<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    /**
     * Lista los negocios a los que pertenece el usuario autenticado.
     * Incluye:
     *  - el negocio asignado (users.business_id),
     *  - el negocio activo (users.current_business_id),
     *  - los negocios cuyo owner es el usuario.
     *
     * @OA\Get(
     *     path="/api/businesses",
     *     tags={"Business"},
     *     summary="Lista los negocios a los que pertenece (o que posee) el usuario autenticado.",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Listado de negocios"),
     *     @OA\Response(response=401, description="No autenticado"),
     * )
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $ids = collect([
            $user->business_id,
            $user->current_business_id,
        ])->filter()->values()->all();

        $businesses = Business::query()
            ->where(function ($q) use ($user, $ids) {
                $q->whereIn('id', $ids)
                  ->orWhere('owner_user_id', $user->id);
            })
            ->get();

        return response()->json($businesses);
    }

    /**
     * Crea un negocio (flujo de onboarding). El usuario autenticado queda
     * registrado como owner y, si aún no tiene business asignado, se le asigna
     * automáticamente este nuevo negocio (como business activo).
     *
     * @OA\Post(
     *     path="/api/businesses",
     *     tags={"Business"},
     *     summary="Crea un nuevo negocio (onboarding). El usuario queda como owner.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="Mi Tienda S.A."),
     *             @OA\Property(property="tax_id", type="string", maxLength=50, nullable=true, example="900123456-7"),
     *             @OA\Property(property="email", type="string", format="email", nullable=true),
     *             @OA\Property(property="phone", type="string", maxLength=30, nullable=true),
     *             @OA\Property(property="address", type="string", maxLength=255, nullable=true),
     *             @OA\Property(property="logo_url", type="string", maxLength=500, nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Negocio creado"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=422, description="Error de validación"),
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'tax_id'    => 'nullable|string|max:50',
            'email'     => 'nullable|email|max:255',
            'phone'     => 'nullable|string|max:30',
            'address'   => 'nullable|string|max:255',
            'logo_url'  => 'nullable|string|max:500',
        ]);

        $user = Auth::user();

        $business = DB::transaction(function () use ($validated, $user) {
            $business = Business::create([
                'name'          => $validated['name'],
                'nit'           => $validated['tax_id'] ?? null,
                'email'         => $validated['email'] ?? null,
                'phone'         => $validated['phone'] ?? null,
                'address'       => $validated['address'] ?? null,
                'logo'          => $validated['logo_url'] ?? null,
                'owner_user_id' => $user->id,
            ]);

            // Si el usuario no tiene negocio asignado, lo asignamos.
            $dirty = false;
            if (!$user->business_id) {
                $user->business_id = $business->id;
                $dirty = true;
            }
            if (!$user->current_business_id) {
                $user->current_business_id = $business->id;
                $dirty = true;
            }
            if ($dirty) {
                $user->save();
            }

            return $business;
        });

        return response()->json($business, 201);
    }

    /**
     * Detalle de un negocio. Solo accesible si el usuario pertenece a él
     * o es su owner.
     *
     * @OA\Get(
     *     path="/api/businesses/{id}",
     *     tags={"Business"},
     *     summary="Devuelve el detalle de un negocio (si el usuario pertenece o es owner).",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Negocio encontrado"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Negocio no encontrado"),
     * )
     */
    public function show($id)
    {
        $business = Business::findOrFail($id);

        if (!$this->userBelongsToBusiness(Auth::user(), $business)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        return response()->json($business);
    }

    /**
     * Actualiza un negocio. Solo owner del business puede modificarlo.
     *
     * @OA\Put(
     *     path="/api/businesses/{id}",
     *     tags={"Business"},
     *     summary="Actualiza los datos de un negocio (solo el owner).",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255),
     *             @OA\Property(property="tax_id", type="string", maxLength=50, nullable=true),
     *             @OA\Property(property="email", type="string", format="email", nullable=true),
     *             @OA\Property(property="phone", type="string", maxLength=30, nullable=true),
     *             @OA\Property(property="address", type="string", maxLength=255, nullable=true),
     *             @OA\Property(property="logo_url", type="string", maxLength=500, nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Negocio actualizado"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Negocio no encontrado"),
     *     @OA\Response(response=422, description="Error de validación"),
     * )
     */
    public function update(Request $request, $id)
    {
        $business = Business::findOrFail($id);
        $user = Auth::user();

        if ($business->owner_user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'tax_id'    => 'sometimes|nullable|string|max:50',
            'email'     => 'sometimes|nullable|email|max:255',
            'phone'     => 'sometimes|nullable|string|max:30',
            'address'   => 'sometimes|nullable|string|max:255',
            'logo_url'  => 'sometimes|nullable|string|max:500',
        ]);

        $payload = [];
        if (array_key_exists('name', $validated))     $payload['name']    = $validated['name'];
        if (array_key_exists('tax_id', $validated))   $payload['nit']     = $validated['tax_id'];
        if (array_key_exists('email', $validated))    $payload['email']   = $validated['email'];
        if (array_key_exists('phone', $validated))    $payload['phone']   = $validated['phone'];
        if (array_key_exists('address', $validated))  $payload['address'] = $validated['address'];
        if (array_key_exists('logo_url', $validated)) $payload['logo']    = $validated['logo_url'];

        $business->update($payload);

        return response()->json($business->fresh());
    }

    /**
     * Soft delete del negocio. Solo el owner puede eliminarlo.
     *
     * @OA\Delete(
     *     path="/api/businesses/{id}",
     *     tags={"Business"},
     *     summary="Elimina (soft delete) un negocio. Solo el owner puede.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Negocio eliminado"),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Negocio no encontrado"),
     * )
     */
    public function destroy($id)
    {
        $business = Business::findOrFail($id);
        $user = Auth::user();

        if ($business->owner_user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $business->delete();

        return response()->json(['message' => 'Negocio eliminado correctamente']);
    }

    /**
     * Cambia el negocio activo del usuario autenticado.
     * Guarda el id en users.current_business_id.
     *
     * @OA\Post(
     *     path="/api/businesses/{id}/switch",
     *     tags={"Business"},
     *     summary="Cambia el negocio activo del usuario autenticado.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Negocio activo actualizado",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="current_business_id", type="integer"),
     *             @OA\Property(property="business", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="No autenticado"),
     *     @OA\Response(response=403, description="No autorizado"),
     *     @OA\Response(response=404, description="Negocio no encontrado"),
     * )
     */
    public function switch($id)
    {
        $business = Business::findOrFail($id);
        $user = Auth::user();

        if (!$this->userBelongsToBusiness($user, $business)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $user->current_business_id = $business->id;
        $user->save();

        return response()->json([
            'message'             => 'Negocio activo actualizado',
            'current_business_id' => $business->id,
            'business'            => $business,
        ]);
    }

    /**
     * Determina si un usuario pertenece (o es owner) a un negocio dado.
     */
    protected function userBelongsToBusiness(User $user, Business $business): bool
    {
        return $business->owner_user_id === $user->id
            || $user->business_id === $business->id
            || $user->current_business_id === $business->id;
    }
}
