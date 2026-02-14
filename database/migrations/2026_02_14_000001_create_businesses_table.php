<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nit')->unique();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('subscription_plan')->default('basic');
            $table->string('subscription_status')->default('active');
            $table->jsonb('plan_limits')->nullable();
            $table->timestamps();
        });

        // CHECK constraints para enums en PostgreSQL
        DB::statement("ALTER TABLE businesses ADD CONSTRAINT businesses_subscription_plan_check CHECK (subscription_plan::text = ANY (ARRAY['basic','pro','enterprise']::text[]))");
        DB::statement("ALTER TABLE businesses ADD CONSTRAINT businesses_subscription_status_check CHECK (subscription_status::text = ANY (ARRAY['active','suspended','cancelled']::text[]))");
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
