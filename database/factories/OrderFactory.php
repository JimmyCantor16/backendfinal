<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'order_number' => 'ORD-' . str_pad((string) fake()->unique()->numberBetween(1, 99999), 4, '0', STR_PAD_LEFT),
            'status'       => 'open',
            'total'        => 0,
            'opened_at'    => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'         => 'closed',
            'payment_method' => 'cash',
            'closed_at'      => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'    => 'cancelled',
            'closed_at' => now(),
        ]);
    }
}
