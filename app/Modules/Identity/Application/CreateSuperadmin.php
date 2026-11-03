<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use InvalidArgumentException;

/**
 * Crea (o actualiza) un superadministrador. Caso de uso del módulo Identity.
 */
class CreateSuperadmin
{
    public function __invoke(string $name, string $email, string $password): User
    {
        if (strlen($password) < 12) {
            throw new InvalidArgumentException('La contraseña del superadministrador debe tener al menos 12 caracteres.');
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = $name;
        $user->password = $password;
        $user->email_verified_at ??= now();
        $user->save();

        $user->assignRole(Role::Superadmin->value);

        return $user;
    }
}
