<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Business;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::first();

        $categories = [
            ['name' => 'Whisky', 'description' => 'Whisky y bourbon'],
            ['name' => 'Ron', 'description' => 'Ron blanco, dorado y añejo'],
            ['name' => 'Vodka', 'description' => 'Vodka nacional e importado'],
            ['name' => 'Aguardiente', 'description' => 'Aguardiente colombiano'],
            ['name' => 'Cerveza', 'description' => 'Cerveza nacional e importada'],
            ['name' => 'Vino', 'description' => 'Vinos tintos, blancos y rosados'],
            ['name' => 'Tequila', 'description' => 'Tequila y mezcal'],
            ['name' => 'Ginebra', 'description' => 'Ginebra y gin'],
        ];

        foreach ($categories as $category) {
            $category['business_id'] = $business->id;
            Category::withoutGlobalScopes()->firstOrCreate(
                ['business_id' => $business->id, 'name' => $category['name']],
                $category
            );
        }
    }
}
