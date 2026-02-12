<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $clients = [
            [
                'document_type' => 'CC',
                'document_number' => '1023456789',
                'name' => 'Juan Rodríguez',
                'phone' => '3201234567',
                'email' => 'juan.rodriguez@email.com',
                'address' => 'Calle 50 #10-20, Bogotá',
            ],
            [
                'document_type' => 'NIT',
                'document_number' => '800555444-1',
                'name' => 'Restaurante El Sabor S.A.S',
                'phone' => '3115556677',
                'email' => 'compras@elsabor.com',
                'address' => 'Carrera 7 #45-12, Bogotá',
            ],
        ];

        foreach ($clients as $client) {
            Client::firstOrCreate(['document_number' => $client['document_number']], $client);
        }
    }
}
