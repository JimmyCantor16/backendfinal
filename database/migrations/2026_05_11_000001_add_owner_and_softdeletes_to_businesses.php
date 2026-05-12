<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columnas requeridas por el módulo Business:
     *  - owner_user_id  (FK a users) en businesses
     *  - soft deletes en businesses
     *  - current_business_id (FK a businesses) en users, para el "switch" de negocio activo
     *
     * No recrea la tabla businesses ni rompe el esquema multitenancy existente.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            if (!Schema::hasColumn('businesses', 'owner_user_id')) {
                $table->unsignedBigInteger('owner_user_id')->nullable()->after('email');
                $table->index('owner_user_id');
            }

            if (!Schema::hasColumn('businesses', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'current_business_id')) {
                $table->unsignedBigInteger('current_business_id')->nullable()->after('business_id');
                $table->index('current_business_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'current_business_id')) {
                $table->dropIndex(['current_business_id']);
                $table->dropColumn('current_business_id');
            }
        });

        Schema::table('businesses', function (Blueprint $table) {
            if (Schema::hasColumn('businesses', 'deleted_at')) {
                $table->dropSoftDeletes();
            }

            if (Schema::hasColumn('businesses', 'owner_user_id')) {
                $table->dropIndex(['owner_user_id']);
                $table->dropColumn('owner_user_id');
            }
        });
    }
};
