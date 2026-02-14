<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'users',
            'categories',
            'suppliers',
            'clients',
            'products',
            'orders',
            'invoices',
            'purchase_orders',
            'inventory_movements',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('business_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('businesses')
                    ->restrictOnDelete();

                $blueprint->index('business_id');
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'inventory_movements',
            'purchase_orders',
            'invoices',
            'orders',
            'products',
            'clients',
            'suppliers',
            'categories',
            'users',
        ];

        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropForeign(["{$table}_business_id_foreign"]);
                $blueprint->dropColumn('business_id');
            });
        }
    }
};
