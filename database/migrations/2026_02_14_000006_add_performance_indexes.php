<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orders: filtrado por status y caja registradora
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['business_id', 'status'], 'orders_business_status_idx');
            $table->index(['business_id', 'status', 'opened_at'], 'orders_business_status_opened_idx');
            $table->index('cash_register_id', 'orders_cash_register_id_idx');
        });

        // Invoices: dashboard y filtros por fecha/status
        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['business_id', 'status', 'created_at'], 'invoices_business_status_created_idx');
        });

        // Cash registers: buscar caja abierta del usuario
        Schema::table('cash_registers', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'cash_registers_user_status_idx');
        });

        // Products: dashboard stock bajo y filtros
        Schema::table('products', function (Blueprint $table) {
            $table->index(['business_id', 'is_active'], 'products_business_active_idx');
        });

        // Purchase orders: filtrado por status
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index(['business_id', 'status'], 'purchase_orders_business_status_idx');
        });

        // Inventory movements: historial por producto y listado
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->index(['business_id', 'product_id'], 'inventory_movements_business_product_idx');
            $table->index(['business_id', 'created_at'], 'inventory_movements_business_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_business_status_idx');
            $table->dropIndex('orders_business_status_opened_idx');
            $table->dropIndex('orders_cash_register_id_idx');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_business_status_created_idx');
        });

        Schema::table('cash_registers', function (Blueprint $table) {
            $table->dropIndex('cash_registers_user_status_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_business_active_idx');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('purchase_orders_business_status_idx');
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex('inventory_movements_business_product_idx');
            $table->dropIndex('inventory_movements_business_created_idx');
        });
    }
};
