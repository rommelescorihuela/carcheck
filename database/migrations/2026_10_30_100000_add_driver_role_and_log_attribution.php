<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rol `driver` para flotas: registra combustible y mantenimiento sin administrar el garaje.
 * Las tablas de registros guardan quién los creó (`user_id`) para poder atribuirlos en la app.
 */
return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL materializa el enum como varchar + CHECK con nombre. SQLite (tests) crea la
        // restricción al vuelo desde la migración original, que ya incluye `driver`.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE organization_user DROP CONSTRAINT IF EXISTS organization_user_role_check');
            DB::statement("ALTER TABLE organization_user ADD CONSTRAINT organization_user_role_check CHECK (role IN ('owner', 'admin', 'member', 'driver'))");

            DB::statement('ALTER TABLE invitations DROP CONSTRAINT IF EXISTS invitations_role_check');
            DB::statement("ALTER TABLE invitations ADD CONSTRAINT invitations_role_check CHECK (role IN ('owner', 'admin', 'member', 'driver'))");
        }

        Schema::table('fuel_entries', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('vehicle_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('vehicle_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fuel_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE organization_user DROP CONSTRAINT IF EXISTS organization_user_role_check');
            DB::statement("ALTER TABLE organization_user ADD CONSTRAINT organization_user_role_check CHECK (role IN ('owner', 'admin', 'member'))");

            DB::statement('ALTER TABLE invitations DROP CONSTRAINT IF EXISTS invitations_role_check');
            DB::statement("ALTER TABLE invitations ADD CONSTRAINT invitations_role_check CHECK (role IN ('owner', 'admin', 'member'))");
        }
    }
};
