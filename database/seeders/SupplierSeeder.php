<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Supplier;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Distribuidora Nacional de Licores',
                'nit' => '900123456-1',
                'phone' => '3001234567',
                'email' => 'ventas@disnalicores.com',
                'address' => 'Calle 10 #25-30, Bogotá',
                'contact_person' => 'Carlos Pérez',
            ],
            [
                'name' => 'Importadora Premium S.A.S',
                'nit' => '900789012-3',
                'phone' => '3109876543',
                'email' => 'contacto@importadorapremium.com',
                'address' => 'Carrera 15 #80-12, Medellín',
                'contact_person' => 'María García',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(['nit' => $supplier['nit']], $supplier);
        }
    }
}
