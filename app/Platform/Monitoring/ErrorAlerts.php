<?php

namespace App\Platform\Monitoring;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Aviso por email cuando la web tiene un error (ERROR_ALERTS=true; docs/despliegue-docker.md).
 *
 * - Lo mismo (clase + archivo + línea) se avisa como mucho una vez cada hora, y nunca más de
 *   MAX_PER_HOUR emails a la hora en total: un fallo repetido no llena el buzón.
 * - El control usa archivos (storage/framework/error-alerts), no la base de datos ni la
 *   caché: así avisa también cuando lo que falla es la base de datos.
 * - Sin datos sensibles: ni lo que escribe el cliente, ni emails, ni tokens, ni el SQL.
 * - Nunca rompe nada: si el correo falla, se calla (el error ya está en el log).
 */
final class ErrorAlerts
{
    public const MAX_PER_HOUR = 10;

    private static bool $sending = false;

    public static function enabled(): bool
    {
        return (bool) config('clavedealma.errors.alerts') && self::recipient() !== null;
    }

    public static function recipient(): ?string
    {
        $email = config('clavedealma.errors.email') ?: config('clavedealma.superadmin.email');

        return is_string($email) && $email !== '' ? $email : null;
    }

    public static function report(Throwable $e): void
    {
        if (self::$sending || ! self::enabled()) {
            return;
        }

        try {
            if (! self::claim(sha1($e::class.'|'.$e->getFile().'|'.$e->getLine()))) {
                return;
            }
            self::send(self::summary($e));
        } catch (Throwable) {
            // El aviso nunca puede causar otro error.
        }
    }

    /** «Enviar un aviso de prueba» del panel: comprueba que el correo llega. */
    public static function test(): void
    {
        $summary = self::summary(new \RuntimeException('Aviso de prueba enviado desde Herramientas → Estado del sistema. No es un error real.'));
        self::send(['title' => 'Aviso de prueba'] + $summary);
    }

    /**
     * Lo que va en el email.
     *
     * @return array<string, mixed>
     */
    public static function summary(Throwable $e): array
    {
        $base = base_path().DIRECTORY_SEPARATOR;
        $trace = collect($e->getTrace())
            ->filter(fn (array $f): bool => isset($f['file']) && ! str_contains((string) $f['file'], DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR))
            ->take(8)
            ->map(fn (array $f): string => str_replace($base, '', (string) $f['file']).':'.($f['line'] ?? '?').' '.(isset($f['class']) ? class_basename((string) $f['class']).'::' : '').$f['function'])
            ->values()->all();

        return [
            'title' => 'Error en la web',
            'type' => $e::class,
            'message' => self::clean($e instanceof QueryException ? Str::before($e->getMessage(), ' (Connection:').' (consulta SQL omitida)' : $e->getMessage()),
            'where' => str_replace($base, '', $e->getFile()).':'.$e->getLine(),
            'context' => self::context(),
            'when' => Carbon::now('Europe/Madrid')->format('d/m/Y H:i:s'),
            'environment' => app()->environment().' · '.config('app.url'),
            'trace' => $trace,
        ];
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private static function send(array $summary): void
    {
        $to = self::recipient();
        if ($to === null) {
            return;
        }

        self::$sending = true;
        try {
            Notification::route('mail', $to)->notifyNow(new ErrorAlertNotification($summary));
        } finally {
            self::$sending = false;
        }
    }

    /** Dónde ha pasado: la página (sin datos), la tarea o la cola. */
    private static function context(): string
    {
        if (app()->runningInConsole()) {
            $argv = (array) ($_SERVER['argv'] ?? []);

            return 'Tarea: php artisan '.self::clean(implode(' ', array_slice($argv, 1, 2)));
        }

        $request = request();
        $user = $request->user();

        return $request->method().' '.self::clean('/'.ltrim($request->path(), '/'))
            .($user !== null ? ' · usuario #'.$user->getAuthIdentifier() : ' · sin sesión');
    }

    /** Quita emails, tokens largos y saltos de línea. */
    private static function clean(string $text): string
    {
        $text = (string) preg_replace('/[^\s@]+@[^\s@]+\.[^\s@]+/', '[email]', $text);
        $text = (string) preg_replace('/[A-Za-z0-9_\-]{32,}/', '[…]', $text);

        return Str::limit(trim((string) preg_replace('/\s+/', ' ', $text)), 500);
    }

    /** Reserva el aviso: false si este error ya se avisó en la última hora o se llegó al tope. */
    private static function claim(string $fingerprint): bool
    {
        $dir = storage_path('framework/error-alerts');
        File::ensureDirectoryExists($dir);
        $hourAgo = time() - 3600;

        $file = $dir.'/'.$fingerprint;
        if (is_file($file) && filemtime($file) > $hourAgo) {
            return false;
        }

        $recent = 0;
        foreach (glob($dir.'/*') ?: [] as $other) {
            $mtime = (int) filemtime($other);
            if ($mtime <= $hourAgo - 86400) {
                @unlink($other); // limpieza de los de hace más de un día
            } elseif ($mtime > $hourAgo) {
                $recent++;
            }
        }
        if ($recent >= self::MAX_PER_HOUR) {
            return false;
        }

        return touch($file);
    }
}
