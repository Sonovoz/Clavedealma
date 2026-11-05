<?php

namespace App\Platform\Payments;

use App\Platform\Settings\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Datos del TPV de Redsys escritos en «Ajustes → Pagos» (ADR 0027), como en el plugin de
 * Redsys para WooCommerce. La clave secreta se guarda cifrada con la APP_KEY y nunca se
 * vuelve a mostrar. Lo del panel manda sobre el .env; lo que el panel no tiene, sale del .env.
 */
final class RedsysSettings
{
    public const KEY = 'payments.redsys';

    public const LAST_NOTICE = 'payments.redsys.last_notice';

    /** Comercio de pruebas que Redsys publica para cualquiera (no es de Clave de Alma). */
    public const TEST_MERCHANT = ['merchant_code' => '999008881', 'terminal' => '1', 'secret' => 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'];

    /**
     * Lo guardado en el panel, sin descifrar.
     *
     * @return array<string, mixed>
     */
    public static function stored(): array
    {
        return (array) Settings::get(self::KEY, []);
    }

    /** Pasa lo del panel a config('payments.redsys') antes de crear la pasarela. */
    public static function apply(): void
    {
        $stored = self::stored();
        if ($stored === []) {
            return;
        }

        $config = (array) config('payments.redsys');
        // Lo que dice el .env, para enseñar de dónde sale cada dato.
        if (config('payments.redsys_env') === null) {
            config(['payments.redsys_env' => $config]);
        }
        $config = array_replace($config, (array) config('payments.redsys_env'));
        foreach (['merchant_code', 'terminal', 'environment', 'merchant_name'] as $key) {
            if (filled($stored[$key] ?? null)) {
                $config[$key] = (string) $stored[$key];
            }
        }
        foreach (['card', 'bizum'] as $key) {
            if (array_key_exists($key, $stored)) {
                $config[$key] = (bool) $stored[$key];
            }
        }
        $secret = self::secret();
        if ($secret !== null) {
            $config['secret_key'] = $secret;
        }

        config(['payments.redsys' => $config]);
    }

    /**
     * Guarda lo del formulario. La clave solo si se escribe una nueva (vacía = se queda la que había).
     *
     * @param  array<string, mixed>  $data
     * @return list<string> Lo que ha cambiado (nombres, nunca valores).
     */
    public static function save(array $data): array
    {
        $before = self::stored();
        $after = $before;
        foreach (['merchant_code', 'terminal', 'merchant_name', 'environment'] as $key) {
            if (array_key_exists($key, $data)) {
                $after[$key] = trim((string) $data[$key]);
            }
        }
        foreach (['card', 'bizum'] as $key) {
            if (array_key_exists($key, $data)) {
                $after[$key] = (bool) $data[$key];
            }
        }
        if (filled($data['secret'] ?? null)) {
            $after['secret'] = Crypt::encryptString(trim((string) $data['secret']));
        }

        $labels = ['merchant_code' => 'código de comercio', 'terminal' => 'terminal', 'secret' => 'clave secreta', 'merchant_name' => 'nombre del comercio', 'environment' => 'entorno', 'card' => 'tarjeta', 'bizum' => 'Bizum'];
        $changed = [];
        foreach ($labels as $key => $label) {
            $was = $key === 'secret' ? self::decrypt($before['secret'] ?? null) : ($before[$key] ?? null);
            $now = $key === 'secret' ? self::decrypt($after['secret'] ?? null) : ($after[$key] ?? null);
            if ($was !== $now) {
                $changed[] = $label;
            }
        }

        Settings::put(self::KEY, $after);
        self::apply();

        return $changed;
    }

    /** La clave secreta (del panel), descifrada; null si no hay o no se puede descifrar. */
    public static function secret(): ?string
    {
        return self::decrypt(self::stored()['secret'] ?? null);
    }

    /**
     * De dónde sale cada dato: «panel», «.env» o null (falta).
     *
     * @return array{merchant_code: ?string, terminal: ?string, secret: ?string}
     */
    public static function sources(): array
    {
        $stored = self::stored();
        $base = (array) (config('payments.redsys_env') ?? config('payments.redsys'));
        $env = [
            'merchant_code' => (string) ($base['merchant_code'] ?? ''),
            'terminal' => (string) ($base['terminal'] ?? ''),
            'secret' => (string) ($base['secret_key'] ?? ''),
        ];

        $from = fn (string $key, bool $panel): ?string => $panel ? 'panel' : ($env[$key] !== '' ? '.env' : null);

        return [
            'merchant_code' => $from('merchant_code', filled($stored['merchant_code'] ?? null)),
            'terminal' => $from('terminal', filled($stored['terminal'] ?? null)),
            'secret' => $from('secret', self::secret() !== null),
        ];
    }

    /** El TPV tiene lo necesario para cobrar (código de comercio, terminal y clave). */
    public static function configured(): bool
    {
        $config = (array) config('payments.redsys');

        return filled($config['merchant_code'] ?? null) && filled($config['terminal'] ?? null) && self::validSecret((string) ($config['secret_key'] ?? ''));
    }

    /** La clave SHA-256 del TPV: Base64 de 24 bytes (32 caracteres). */
    public static function validSecret(string $secret): bool
    {
        $raw = base64_decode(trim($secret), true);

        return $raw !== false && strlen($raw) === 24;
    }

    /** Se está usando el comercio público de pruebas de Redsys. */
    public static function usingTestMerchant(): bool
    {
        return (string) config('payments.redsys.merchant_code') === self::TEST_MERCHANT['merchant_code'];
    }

    private static function decrypt(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            // Pasa si cambia la APP_KEY: hay que volver a escribir la clave en el panel.
            Log::error('No se puede descifrar la clave del TPV de Redsys guardada en el panel (¿ha cambiado la APP_KEY?).');

            return null;
        }
    }
}
