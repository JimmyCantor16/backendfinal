<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'client_id'      => Client::factory(),
            'user_id'        => User::factory(),
            'invoice_number' => 'FAC-' . str_pad((string) fake()->unique()->numberBetween(1, 99999), 4, '0', STR_PAD_LEFT),
            'subtotal'       => 100.00,
            'iva_rate'       => 19.00,
            'iva'            => 19.00,
            'total'          => 119.00,
            'payment_method' => 'cash',
            'status'         => 'completed',
            'notes'          => null,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}
