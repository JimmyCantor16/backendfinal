<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Planes base del SaaS. `stripe_price_id` queda null hasta que el
     * operador cree los precios en Stripe Dashboard con sus credenciales
     * y los registre aquí (vía artisan tinker, admin, o env).
     */
    public function run(): void
    {
        $plans = [
            [
                'name'            => 'Free',
                'slug'            => 'free',
                'price_cents'     => 0,
                'currency'        => 'usd',
                'interval'        => 'month',
                'stripe_price_id' => null,
                'features'        => [
                    'max_users'    => 2,
                    'max_products' => 50,
                    'support'      => 'community',
                ],
                'active'          => true,
            ],
            [
                'name'            => 'Pro',
                'slug'            => 'pro',
                'price_cents'     => 2900, // $29.00 USD/month
                'currency'        => 'usd',
                'interval'        => 'month',
                'stripe_price_id' => null,
                'features'        => [
                    'max_users'    => 20,
                    'max_products' => 5000,
                    'support'      => 'email',
                ],
                'active'          => true,
            ],
            [
                'name'            => 'Enterprise',
                'slug'            => 'enterprise',
                'price_cents'     => 9900, // $99.00 USD/month
                'currency'        => 'usd',
                'interval'        => 'month',
                'stripe_price_id' => null,
                'features'        => [
                    'max_users'    => null,   // ilimitado
                    'max_products' => null,
                    'support'      => 'priority',
                ],
                'active'          => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
