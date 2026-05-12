<?php

namespace App\Http\Controllers\Api;

use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

/**
 * Stripe Webhook handler.
 *
 * Extiende el controller de Cashier para poder sobreescribir handlers
 * (handleCustomerSubscriptionCreated, handleCustomerSubscriptionUpdated, etc.)
 * en el futuro si necesitamos lógica custom. Por ahora delega al padre,
 * que ya implementa todos los eventos default de Cashier.
 *
 * La auth de este endpoint NO es Sanctum: Cashier verifica la firma del
 * webhook usando STRIPE_WEBHOOK_SECRET (config('cashier.webhook.secret')).
 */
class StripeWebhookController extends CashierWebhookController
{
    //
}
