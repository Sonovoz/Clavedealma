<?php

namespace App\Console\Commands;

use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Emergencia (ADR 0028): quita la verificación en dos pasos de una cuenta desde el servidor,
 * por ejemplo si el único superadministrador pierde el móvil y los códigos de recuperación.
 * Queda en el registro de seguridad (SecurityListeners).
 */
#[Signature('usuarios:quitar-dos-pasos {email : Email de la cuenta}')]
#[Description('Quita la verificación en dos pasos de una cuenta (si se ha perdido el móvil)')]
class ResetTwoFactorCommand extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->first();
        if ($user === null) {
            $this->error('No hay ninguna cuenta con ese email.');

            return self::FAILURE;
        }
        if (! $user->hasTwoFactor()) {
            $this->info('Esa cuenta no tiene la verificación en dos pasos activada.');

            return self::SUCCESS;
        }

        $user->saveAppAuthenticationSecret(null);
        $user->saveAppAuthenticationRecoveryCodes(null);
        $this->info("Verificación en dos pasos quitada a {$user->email}. Al entrar tendrá que configurarla de nuevo.");

        return self::SUCCESS;
    }
}
