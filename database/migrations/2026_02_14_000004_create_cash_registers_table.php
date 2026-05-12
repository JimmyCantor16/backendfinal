<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('opening_amount', 14, 2)->default(0);
            $table->decimal('closing_amount', 14, 2)->nullable();
            $table->decimal('total_cash', 14, 2)->default(0);
            $table->decimal('total_card', 14, 2)->default(0);
            $table->decimal('total_transfer', 14, 2)->default(0);
            $table->decimal('total_qr', 14, 2)->default(0);
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->string('status')->default('open');
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('business_id');
        });

        // CHECK constraint solo para PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE cash_registers ADD CONSTRAINT cash_registers_status_check CHECK (status::text = ANY (ARRAY['open','closed']::text[]))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_registers');
    }
};
