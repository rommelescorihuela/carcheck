<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Role;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email} {--name=Administrador}';

    protected $description = 'Crea o promueve un super administrador del panel /admin.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $validator = Validator::make(['email' => $email], ['email' => ['required', 'email']]);

        if ($validator->fails()) {
            $this->error('El email no es válido.');

            return self::FAILURE;
        }

        $user = User::withTrashed()->firstWhere('email', $email);

        if ($user === null) {
            $password = (string) $this->secret('Contraseña (mínimo 8 caracteres)');
            $confirmation = (string) $this->secret('Repite la contraseña');

            if (strlen($password) < 8) {
                $this->error('La contraseña debe tener al menos 8 caracteres.');

                return self::FAILURE;
            }

            if ($password !== $confirmation) {
                $this->error('Las contraseñas no coinciden.');

                return self::FAILURE;
            }

            $user = User::create([
                'name' => (string) $this->option('name'),
                'email' => $email,
                'password' => $password,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->info("Cuenta creada: {$email}");
        } else {
            if ($user->trashed()) {
                $user->restore();
                $this->info('La cuenta estaba eliminada y se ha restaurado.');
            }

            $this->info("La cuenta ya existía; se conserva su contraseña: {$email}");
        }

        $role = Role::findOrCreate('super_admin', Guard::getDefaultName($user));
        $user->assignRole($role);

        $this->info('Rol asignado: super_admin. Ya puede entrar en /admin.');

        return self::SUCCESS;
    }
}
