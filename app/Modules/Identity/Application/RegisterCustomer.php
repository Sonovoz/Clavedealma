<?php

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Alta de un cliente («Registrarse» en «Mi cuenta» o «¿Crear una cuenta?» al finalizar
 * la compra). Solo tiene el rol Cliente: nunca entra en el panel. Se le envía el email
 * para confirmar su dirección (Registered → verificación).
 */
class RegisterCustomer
{
    public function __invoke(string $email, string $password, ?string $name = null): User
    {
        $email = mb_strtolower(trim($email));
        if (User::query()->where('email', $email)->exists()) {
            throw new InvalidArgumentException('Ya hay una cuenta registrada con tu dirección de correo electrónico.');
        }

        $user = User::query()->create([
            // Como WooCommerce: si no da nombre, se usa la parte del email antes de la @.
            'name' => $name !== null && trim($name) !== '' ? trim($name) : Str::before($email, '@'),
            'email' => $email,
            'password' => $password,
        ]);
        $user->assignRole(Role::Customer->value);

        event(new Registered($user));

        return $user;
    }
}
