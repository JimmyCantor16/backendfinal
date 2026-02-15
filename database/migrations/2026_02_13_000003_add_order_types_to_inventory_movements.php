<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Solo PostgreSQL necesita re-crear el CHECK constraint; en SQLite
        // la migración original ya incluye todos los valores del enum.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inventory_movements DROP CONSTRAINT inventory_movements_type_check");
            DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_type_check CHECK (type::text = ANY (ARRAY['purchase_in','sale_out','adjustment','order_out','order_return']::text[]))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE inventory_movements DROP CONSTRAINT inventory_movements_type_check");
            DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT inventory_movements_type_check CHECK (type::text = ANY (ARRAY['purchase_in','sale_out','adjustment']::text[]))");
        }
    }
};
