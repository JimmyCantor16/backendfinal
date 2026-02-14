<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type');           // e.g. "Order", "CashRegister"
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action');                 // e.g. "created", "closed", "cancelled"
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes para consultas frecuentes
            $table->index(['business_id', 'created_at'], 'audit_logs_business_created_idx');
            $table->index(['entity_type', 'entity_id'], 'audit_logs_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
