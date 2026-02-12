<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
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
            Category::firstOrCreate(['name' => $category['name']], $category);
        }
    }
}
