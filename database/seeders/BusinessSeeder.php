<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Business;

class BusinessSeeder extends Seeder
{
    public function run(): void
    {
        Business::firstOrCreate(
            ['nit' => '900000001-1'],
            [
                'name' => 'Bar Demo',
                'address' => 'Calle 80 #15-20, Bogotá',
                'phone' => '3001112233',
                'email' => 'admin@bardemo.com',
                'subscription_plan' => 'pro',
                'subscription_status' => 'active',
                'plan_limits' => [
                    'max_products' => 500,
                    'max_users' => 10,
                    'features' => ['pos', 'inventory', 'invoicing', 'reports'],
                ],
            ]
        );
    }
}
