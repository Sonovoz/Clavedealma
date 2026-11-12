<?php

namespace App\Platform\Install;

use App\Modules\Identity\Application\CreateSuperadmin;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * El último paso del instalador (ADR 0030): con los datos ya guardados en el .env, crea las
 * tablas, los roles y el superadministrador, enlaza los archivos públicos y marca la web como
 * instalada. Devuelve la clave de las tareas programadas por URL.
 */
class RunInstall
{
    public function __construct(
        private readonly EnvFile $env,
        private readonly InstallState $state,
        private readonly CreateSuperadmin $createSuperadmin,
    ) {}

    /**
     * @param  array{url: string, name: string, email: string, password: string}  $data
     */
    public function __invoke(array $data): string
    {
        @set_time_limit(300);

        $url = rtrim($data['url'], '/');
        $host = (string) parse_url($url, PHP_URL_HOST);
        $token = $this->env->get('CRON_TOKEN') ?: Str::random(40);
        $this->env->set([
            'APP_URL' => $url,
            'APP_DOMAIN' => (string) preg_replace('/^www\./', '', $host),
            'SESSION_SECURE_COOKIE' => str_starts_with($url, 'https://') ? 'true' : 'false',
            'CRON_TOKEN' => $token,
        ]);

        $this->useDatabaseFromEnv();
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException('No se han podido crear las tablas: '.trim(Artisan::output()));
        }
        Artisan::call('db:seed', ['--class' => RolesSeeder::class, '--force' => true]);
        ($this->createSuperadmin)($data['name'], $data['email'], $data['password']);
        if (! is_link(public_path('storage')) && ! is_dir(public_path('storage'))) {
            Artisan::call('storage:link');
        }
        Artisan::call('config:clear');

        $this->state->markInstalled();

        return $token;
    }

    /** La conexión de este mismo proceso, con lo que se acaba de escribir en el .env. */
    private function useDatabaseFromEnv(): void
    {
        $e = $this->env->read();
        $connection = $e['DB_CONNECTION'] ?? 'mariadb';
        if (in_array($connection, ['mariadb', 'mysql'], true)) {
            config([
                'database.default' => $connection,
                "database.connections.{$connection}.host" => $e['DB_HOST'] ?? 'mariadb',
                "database.connections.{$connection}.port" => $e['DB_PORT'] ?? '3306',
                "database.connections.{$connection}.database" => $e['DB_DATABASE'] ?? '',
                "database.connections.{$connection}.username" => $e['DB_USERNAME'] ?? '',
                "database.connections.{$connection}.password" => $e['DB_PASSWORD'] ?? '',
            ]);
            DB::purge($connection);
        }
    }
}
