<?php

namespace App\Platform\Mail;

use App\Platform\Settings\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Correo de salida por SMTP para toda la web (ADR 0029): pedidos, facturas, notas, avisos
 * de artistas, copias, errores y Minimedia salen todos por aquí. Los datos se escriben en
 * «Ajustes → Correo» (la contraseña cifrada con la APP_KEY, nunca se vuelve a mostrar) y
 * mandan sobre el .env; lo que el panel no tiene, sale del .env (MAIL_*).
 */
final class SmtpSettings
{
    public const KEY = 'mail.smtp';

    /**
     * @return array<string, mixed>
     */
    public static function stored(): array
    {
        return (array) Settings::get(self::KEY, []);
    }

    /** Pasa lo del panel a config('mail.*') antes de crear el correo. */
    public static function apply(): void
    {
        $s = self::stored();
        if ($s === []) {
            return;
        }

        $map = ['host' => 'mail.mailers.smtp.host', 'port' => 'mail.mailers.smtp.port', 'username' => 'mail.mailers.smtp.username',
            'from_address' => 'mail.from.address', 'from_name' => 'mail.from.name'];
        foreach ($map as $key => $path) {
            if (filled($s[$key] ?? null)) {
                config([$path => $key === 'port' ? (int) $s[$key] : (string) $s[$key]]);
            }
        }
        if (filled($s['encryption'] ?? null)) {
            // SSL/TLS (465) → smtps; STARTTLS (587) → smtp, que pasa a cifrado solo.
            config(['mail.mailers.smtp.scheme' => $s['encryption'] === 'ssl' ? 'smtps' : 'smtp']);
        }
        $password = self::password();
        if ($password !== null) {
            config(['mail.mailers.smtp.password' => $password]);
        }
    }

    /**
     * Guarda el formulario. La contraseña solo si se escribe una nueva.
     *
     * @param  array<string, mixed>  $data
     * @return list<string> Lo que ha cambiado (nombres, nunca valores).
     */
    public static function save(array $data): array
    {
        $before = self::stored();
        $after = $before;
        foreach (['host', 'port', 'encryption', 'username', 'from_address', 'from_name'] as $key) {
            if (array_key_exists($key, $data)) {
                $after[$key] = trim((string) $data[$key]);
            }
        }
        if (filled($data['password'] ?? null)) {
            $after['password'] = Crypt::encryptString((string) $data['password']);
        }

        $labels = ['host' => 'servidor', 'port' => 'puerto', 'encryption' => 'cifrado', 'username' => 'usuario', 'password' => 'contraseña', 'from_address' => 'remitente', 'from_name' => 'nombre del remitente'];
        $changed = [];
        foreach ($labels as $key => $label) {
            $was = $key === 'password' ? self::decrypt($before[$key] ?? null) : ($before[$key] ?? null);
            $now = $key === 'password' ? self::decrypt($after[$key] ?? null) : ($after[$key] ?? null);
            if ($was !== $now) {
                $changed[] = $label;
            }
        }

        Settings::put(self::KEY, $after);
        self::apply();
        // El correo ya creado en esta petición se rehace con los datos nuevos.
        if (! Mail::isFake()) {
            Mail::purge('smtp');
        }

        return $changed;
    }

    public static function password(): ?string
    {
        return self::decrypt(self::stored()['password'] ?? null);
    }

    /** De dónde salen los datos: «panel», «.env» o null (sin configurar). */
    public static function source(): ?string
    {
        if (filled(self::stored()['host'] ?? null)) {
            return 'panel';
        }
        $host = (string) config('mail.mailers.smtp.host');

        return $host !== '' && $host !== '127.0.0.1' ? '.env' : null;
    }

    /** La web envía por SMTP (MAIL_MAILER=smtp). */
    public static function usesSmtp(): bool
    {
        return config('mail.default') === 'smtp';
    }

    private static function decrypt(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            Log::error('No se puede descifrar la contraseña del correo guardada en el panel (¿ha cambiado la APP_KEY?).');

            return null;
        }
    }
}
