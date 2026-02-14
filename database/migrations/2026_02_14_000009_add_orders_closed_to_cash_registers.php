<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->unsignedInteger('orders_closed')->default(0)->after('total_sales');
        });
    }

    public function down(): void
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropColumn('orders_closed');
        });
    }
};
