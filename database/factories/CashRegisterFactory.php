<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CashRegister>
 */
class CashRegisterFactory extends Factory
{
    protected $model = CashRegister::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'opening_amount' => 100000,
            'closing_amount' => null,
            'total_cash'     => 0,
            'total_card'     => 0,
            'total_transfer' => 0,
            'total_qr'       => 0,
            'total_sales'    => 0,
            'orders_closed'  => 0,
            'status'         => 'open',
            'opened_at'      => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'         => 'closed',
            'closing_amount' => 100000,
            'closed_at'      => now(),
        ]);
    }
}
