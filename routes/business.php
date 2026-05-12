<?php

use App\Http\Controllers\Api\BusinessController;
use App\Http\Middleware\EnsureBusinessContext;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Business Module Routes (Multi-tenant)
|--------------------------------------------------------------------------
|
| Este archivo contiene las rutas del módulo Business. NO se carga
| automáticamente: debe ser registrado desde RouteServiceProvider o
| incluido manualmente desde routes/api.php (por ejemplo:
| `require __DIR__.'/business.php';` dentro de la sección /api).
|
| Las rutas asumen el prefijo /api añadido por RouteServiceProvider
| cuando se incluyen dentro del grupo api.
|
*/

Route::middleware(['auth:sanctum'])->prefix('businesses')->group(function () {
    // Listado / creación
    Route::get('/',         [BusinessController::class, 'index']);
    Route::post('/',        [BusinessController::class, 'store']);

    // Cambio de negocio activo (switch)
    Route::post('/{id}/switch', [BusinessController::class, 'switch'])
        ->whereNumber('id');

    // CRUD por id
    Route::get('/{id}',     [BusinessController::class, 'show'])->whereNumber('id');
    Route::put('/{id}',     [BusinessController::class, 'update'])->whereNumber('id');
    Route::patch('/{id}',   [BusinessController::class, 'update'])->whereNumber('id');
    Route::delete('/{id}',  [BusinessController::class, 'destroy'])->whereNumber('id');
});

/*
|--------------------------------------------------------------------------
| Middleware alias sugerido (a registrar en Http/Kernel.php cuando
| se cableé el contexto multi-tenant a los recursos):
|--------------------------------------------------------------------------
|
| 'business.context' => \App\Http\Middleware\EnsureBusinessContext::class,
|
| Uso: Route::middleware(['auth:sanctum','business.context'])->group(...)
|
*/
