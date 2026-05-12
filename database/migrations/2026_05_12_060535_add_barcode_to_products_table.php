<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Idempotente: si la columna ya existe (porque la migración corrió antes
     * o se introdujo manualmente), no se vuelve a crear ni se duplica el
     * índice. Esto previene errores en re-deploys parciales.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'barcode')) {
            Schema::table('products', function (Blueprint $table) {
                // VARCHAR(64) — suficiente para EAN-13, UPC-A, Code128, QR cortos
                $table->string('barcode', 64)->nullable()->after('sku');
                $table->index('barcode', 'products_barcode_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('products', 'barcode')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_barcode_index');
                $table->dropColumn('barcode');
            });
        }
    }
};
