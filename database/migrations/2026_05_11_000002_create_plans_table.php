<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 8)->default('usd');
            $table->string('interval', 16)->default('month'); // 'month' | 'year'
            $table->string('stripe_price_id')->nullable();
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
