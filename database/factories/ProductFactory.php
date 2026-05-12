<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id'    => Category::factory(),
            'name'           => fake()->words(3, true),
            'sku'            => strtoupper(fake()->unique()->bothify('SKU-####??')),
            'description'    => fake()->sentence(),
            'purchase_price' => fake()->randomFloat(2, 1, 500),
            'sale_price'     => fake()->randomFloat(2, 10, 1000),
            'stock'          => fake()->numberBetween(10, 100),
            'min_stock'      => 5,
            'is_active'      => true,
        ];
    }
}
