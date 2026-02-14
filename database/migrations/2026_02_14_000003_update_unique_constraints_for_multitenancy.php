<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // categories.name → (business_id, name)
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique(['business_id', 'name']);
        });

        // suppliers.nit → (business_id, nit)
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['nit']);
            $table->unique(['business_id', 'nit']);
        });

        // clients.document_number → (business_id, document_number)
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['document_number']);
            $table->unique(['business_id', 'document_number']);
        });

        // products.sku → (business_id, sku)
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->unique(['business_id', 'sku']);
        });

        // orders.order_number → (business_id, order_number)
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            $table->unique(['business_id', 'order_number']);
        });

        // invoices.invoice_number → (business_id, invoice_number)
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->unique(['business_id', 'invoice_number']);
        });

        // purchase_orders.order_number → (business_id, order_number)
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            $table->unique(['business_id', 'order_number']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'order_number']);
            $table->unique('order_number');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'invoice_number']);
            $table->unique('invoice_number');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'order_number']);
            $table->unique('order_number');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'sku']);
            $table->unique('sku');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'document_number']);
            $table->unique('document_number');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'nit']);
            $table->unique('nit');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['business_id', 'name']);
            $table->unique('name');
        });
    }
};
