<?php

namespace App\Platform\Security;

use App\Modules\Identity\Application\CreateSuperadmin;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Install\EnvFile;
use App\Platform\Settings\Settings;
use InvalidArgumentException;

/**
 * Recuperar el acceso al panel sin línea de comandos, como el modo de recuperación de WordPress
 * (ADR 0031). Solo funciona si alguien con acceso al servidor escribe en el .env una clave
 * RECUPERAR_ACCESO (20 caracteres o más). Se usa una vez: después se borra del .env y, si no se
 * puede borrar, queda anotada como gastada.
 *
 * Se lee del archivo .env directamente (no de la configuración en caché), para que valga en
 * cuanto se guarda.
 */
class RecoverAccess
{
    public const ENV = 'RECUPERAR_ACCESO';

    private const USED = 'security.recovery_used';

    public function __construct(
        private readonly EnvFile $env,
        private readonly CreateSuperadmin $createSuperadmin,
    ) {}

    public function enabled(): bool
    {
        $key = $this->key();

        return strlen($key) >= 20 && ! in_array(hash('sha256', $key), (array) Settings::get(self::USED, []), true);
    }

    public function matches(string $given): bool
    {
        return $this->enabled() && hash_equals($this->key(), $given);
    }

    /** Pone la contraseña (crea la cuenta si no existe), quita los dos pasos y gasta la clave. */
    public function __invoke(string $given, string $email, string $password): User
    {
        if (! $this->matches($given)) {
            throw new InvalidArgumentException('La clave de recuperación no es correcta.');
        }

        $existing = User::query()->where('email', $email)->first();
        $user = ($this->createSuperadmin)($existing?->name ?: 'Superadmin', $email, $password);
        if ($user->hasTwoFactor()) {
            $user->saveAppAuthenticationSecret(null);
            $user->saveAppAuthenticationRecoveryCodes(null);
        }

        Settings::put(self::USED, array_values(array_unique([...(array) Settings::get(self::USED, []), hash('sha256', $this->key())])));
        if ($this->env->writable()) {
            $this->env->set([self::ENV => '']);
        }

        SecurityLog::record(SecurityEventType::AccessRecovered, 'Acceso recuperado con la clave del .env: contraseña nueva'.($existing ? '' : ' (cuenta creada)').' y dos pasos por configurar.', $email, user: $user);

        return $user;
    }

    private function key(): string
    {
        return trim((string) $this->env->get(self::ENV, ''));
    }
}
