<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Business>
 */
class BusinessFactory extends Factory
{
    protected $model = Business::class;

    public function definition(): array
    {
        return [
            'name'                => fake()->company(),
            'nit'                 => fake()->unique()->numerify('#########'),
            'address'             => fake()->address(),
            'phone'               => fake()->phoneNumber(),
            'email'               => fake()->unique()->safeEmail(),
            'subscription_plan'   => 'basic',
            'subscription_status' => 'active',
            'plan_limits'         => null,
        ];
    }
}
