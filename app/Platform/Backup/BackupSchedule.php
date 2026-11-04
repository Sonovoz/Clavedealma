<?php

namespace App\Platform\Backup;

use App\Platform\Settings\Settings;
use Carbon\CarbonInterface;

/**
 * Programación de las copias, elegida en el panel. Por defecto: cada día a las 03:15
 * (hora de Madrid) y se conservan las 14 últimas.
 *
 * @phpstan-type Schedule array{enabled: bool, frequency: string, weekday: int, time: string, keep: int, email: ?string}
 */
final class BackupSchedule
{
    public const KEY = 'backups.schedule';

    public const DEFAULTS = ['enabled' => true, 'frequency' => 'daily', 'weekday' => 1, 'time' => '03:15', 'keep' => 14, 'email' => null];

    public const WEEKDAYS = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 0 => 'domingo'];

    /**
     * @return Schedule
     */
    public static function get(): array
    {
        $saved = (array) Settings::get(self::KEY, []);

        return [
            'enabled' => (bool) ($saved['enabled'] ?? self::DEFAULTS['enabled']),
            'frequency' => ($saved['frequency'] ?? '') === 'weekly' ? 'weekly' : 'daily',
            'weekday' => (int) ($saved['weekday'] ?? self::DEFAULTS['weekday']),
            'time' => preg_match('/^\d{2}:\d{2}$/', (string) ($saved['time'] ?? '')) ? (string) $saved['time'] : self::DEFAULTS['time'],
            'keep' => max(1, min(365, (int) ($saved['keep'] ?? self::DEFAULTS['keep']))),
            'email' => filled($saved['email'] ?? null) ? (string) $saved['email'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values): void
    {
        Settings::put(self::KEY, array_intersect_key($values + self::get(), self::DEFAULTS));
    }

    /** Expresión cron para el programador de Laravel. */
    public static function cron(): string
    {
        $s = self::get();
        [$hour, $minute] = array_map('intval', explode(':', $s['time']));

        return "{$minute} {$hour} * * ".($s['frequency'] === 'weekly' ? (string) $s['weekday'] : '*');
    }

    public static function describe(): string
    {
        $s = self::get();
        if (! $s['enabled']) {
            return 'Copias automáticas desactivadas.';
        }
        $when = $s['frequency'] === 'weekly' ? 'Cada '.self::WEEKDAYS[$s['weekday']] : 'Cada día';

        return "{$when} a las {$s['time']} (hora de Madrid). Se conservan las {$s['keep']} últimas.";
    }

    /** Antigüedad máxima razonable de la última copia buena antes de avisar. */
    public static function staleAfter(CarbonInterface $now): CarbonInterface
    {
        return self::get()['frequency'] === 'weekly' ? $now->copy()->subDays(8) : $now->copy()->subHours(36);
    }

    /** Destinatario de los avisos: el del panel o el superadmin del .env. */
    public static function notifyEmail(): ?string
    {
        return self::get()['email'] ?? (config('clavedealma.superadmin.email') ?: null);
    }
}
