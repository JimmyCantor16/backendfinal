<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\Business;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::first();
        $categories = Category::withoutGlobalScopes()->where('business_id', $business->id)->pluck('id', 'name');

        $products = [
            [
                'category' => 'Whisky',
                'name' => 'Johnnie Walker Red Label 750ml',
                'sku' => 'WH-JWR-750',
                'purchase_price' => 65000,
                'sale_price' => 89900,
                'stock' => 20,
                'min_stock' => 5,
            ],
            [
                'category' => 'Ron',
                'name' => 'Ron Medellín Añejo 750ml',
                'sku' => 'RN-MED-750',
                'purchase_price' => 28000,
                'sale_price' => 39900,
                'stock' => 30,
                'min_stock' => 10,
            ],
            [
                'category' => 'Aguardiente',
                'name' => 'Aguardiente Antioqueño 750ml',
                'sku' => 'AG-ANT-750',
                'purchase_price' => 22000,
                'sale_price' => 32900,
                'stock' => 50,
                'min_stock' => 15,
            ],
            [
                'category' => 'Cerveza',
                'name' => 'Club Colombia Dorada Six Pack',
                'sku' => 'CR-CCD-6PK',
                'purchase_price' => 12000,
                'sale_price' => 18900,
                'stock' => 40,
                'min_stock' => 20,
            ],
            [
                'category' => 'Vodka',
                'name' => 'Absolut Original 750ml',
                'sku' => 'VK-ABS-750',
                'purchase_price' => 48000,
                'sale_price' => 65900,
                'stock' => 15,
                'min_stock' => 5,
            ],
        ];

        foreach ($products as $data) {
            $categoryId = $categories[$data['category']] ?? null;
            if (!$categoryId) continue;

            Product::withoutGlobalScopes()->firstOrCreate(
                ['business_id' => $business->id, 'sku' => $data['sku']],
                [
                    'business_id' => $business->id,
                    'category_id' => $categoryId,
                    'name' => $data['name'],
                    'purchase_price' => $data['purchase_price'],
                    'sale_price' => $data['sale_price'],
                    'stock' => $data['stock'],
                    'min_stock' => $data['min_stock'],
                ]
            );
        }
    }
}
