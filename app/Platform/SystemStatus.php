<?php

namespace App\Platform;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Estado técnico de la instalación, para la página Sistema del panel.
 */
class SystemStatus
{
    public function __construct(private readonly Migrator $migrator) {}

    public function version(): string
    {
        $file = base_path('VERSION');

        return is_file($file) ? trim((string) file_get_contents($file)) : 'desarrollo';
    }

    public function databaseIsReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function pendingMigrations(): int
    {
        if (! $this->databaseIsReachable() || ! $this->migrator->repositoryExists()) {
            return count($this->migrationFiles());
        }

        $ran = $this->migrator->getRepository()->getRan();

        return count(array_diff(array_keys($this->migrationFiles()), $ran));
    }

    public function storageIsLinked(): bool
    {
        return is_link(public_path('storage')) || is_dir(public_path('storage'));
    }

    public function storageIsWritable(): bool
    {
        return is_writable(storage_path('framework')) && is_writable(base_path('bootstrap/cache'));
    }

    public function isInMaintenance(): bool
    {
        return app()->isDownForMaintenance();
    }

    /**
     * @return array<string, string>
     */
    public function drivers(): array
    {
        return [
            'Base de datos' => (string) config('database.default'),
            'Caché' => (string) config('cache.default'),
            'Sesiones' => (string) config('session.driver'),
            'Colas' => (string) config('queue.default'),
            'Archivos' => (string) config('filesystems.default'),
            'Correo' => (string) config('mail.default'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function migrationFiles(): array
    {
        return $this->migrator->getMigrationFiles(
            array_merge($this->migrator->paths(), [database_path('migrations')])
        );
    }
}
