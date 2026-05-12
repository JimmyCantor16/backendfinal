<?php

use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Subscription / Billing Module Routes
|--------------------------------------------------------------------------
|
| Este archivo se incluye desde routes/api.php con
| `require __DIR__.'/subscription.php';` al final.
|
| - /plans*               : públicos (catálogo de planes; sin auth para
|                           que el frontend pueda mostrar la página de
|                           precios sin sesión).
| - /subscriptions/*      : protegidos con auth:sanctum.
| - /stripe/webhook       : SIN auth Sanctum: Stripe firma el request y
|                           Cashier verifica con STRIPE_WEBHOOK_SECRET.
|
*/

// --- Plans (catálogo público, solo lectura) -------------------------------
Route::prefix('plans')->group(function () {
    Route::get('/',      [PlanController::class, 'index']);
    Route::get('/{id}',  [PlanController::class, 'show'])->whereNumber('id');
});

// --- Subscriptions (auth requerida) ---------------------------------------
Route::middleware(['auth:sanctum'])->prefix('subscriptions')->group(function () {
    Route::get('/current',   [SubscriptionController::class, 'current']);
    Route::post('/checkout', [SubscriptionController::class, 'checkout']);
    Route::post('/cancel',   [SubscriptionController::class, 'cancel']);
    Route::post('/resume',   [SubscriptionController::class, 'resume']);
});

// --- Stripe webhook (sin auth) --------------------------------------------
// Cashier valida la firma con STRIPE_WEBHOOK_SECRET; no debe pasar por
// middleware de auth.
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('cashier.webhook')
    ->withoutMiddleware(['auth:sanctum']);
