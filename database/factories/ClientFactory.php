<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'document_type'   => 'CC',
            'document_number' => fake()->unique()->numerify('##########'),
            'name'            => fake()->name(),
            'phone'           => fake()->phoneNumber(),
            'email'           => fake()->unique()->safeEmail(),
            'address'         => fake()->address(),
        ];
    }
}
