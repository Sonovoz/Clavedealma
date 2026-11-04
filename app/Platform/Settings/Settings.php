<?php

namespace App\Platform\Settings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Ajustes de la plataforma que se cambian desde el panel (tabla `settings`).
 */
final class Settings
{
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = DB::table('settings')->where('key', $key)->value('value');
        } catch (Throwable) {
            return $default; // p. ej. antes de migrar
        }

        return $value === null ? $default : json_decode((string) $value, true);
    }

    public static function put(string $key, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => $key],
            ['value' => json_encode($value, JSON_UNESCAPED_UNICODE), 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public static function ready(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }
}
