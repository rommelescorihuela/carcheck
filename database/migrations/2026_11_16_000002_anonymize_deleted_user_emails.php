<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Los usuarios se borran con soft delete y la columna `email` es única. Si se
 * conserva el correo original, esa persona no puede volver a registrarse nunca.
 * Las cuentas ya eliminadas se anonimizan aquí; el borrado nuevo lo hace
 * `AccountController`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->where('email', 'not like', 'deleted-%@users.invalid')
            ->update([
                'email' => DB::raw("concat('deleted-', id, '@users.invalid')"),
            ]);
    }

    public function down(): void
    {
        // No se puede recuperar el correo original de una cuenta anonimizada.
    }
};
