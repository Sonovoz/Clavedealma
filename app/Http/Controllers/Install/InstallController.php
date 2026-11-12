<?php

namespace App\Http\Controllers\Install;

use App\Platform\Install\DatabaseProbe;
use App\Platform\Install\EnvFile;
use App\Platform\Install\Requirements;
use App\Platform\Install\RunInstall;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * Instalador web (ADR 0030), como el de WordPress: 1) requisitos del servidor, 2) base de
 * datos, 3) sitio y administrador, 4) listo. Solo existe mientras la web no está instalada
 * (RedirectToInstaller); después, devuelve 404.
 */
class InstallController
{
    public function __construct(private readonly EnvFile $env) {}

    public function requirements(Requirements $requirements): View
    {
        return view('install.requirements', ['rows' => $requirements->check(), 'passes' => $requirements->passes()]);
    }

    public function database(Requirements $requirements): View|RedirectResponse
    {
        if (! $requirements->passes()) {
            return redirect('/instalar');
        }
        $e = $this->env->read();

        return view('install.database', [
            'host' => $e['DB_HOST'] ?? 'mariadb', 'port' => $e['DB_PORT'] ?? '3306',
            'database' => $e['DB_DATABASE'] ?? '', 'username' => $e['DB_USERNAME'] ?? '',
        ]);
    }

    public function saveDatabase(Request $request, DatabaseProbe $probe): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'username' => ['required', 'string', 'max:80'],
            'password' => ['nullable', 'string', 'max:190'],
        ], [
            'host.regex' => 'El servidor solo puede llevar letras, números, puntos y guiones (por ejemplo «mariadb»).',
            'database.regex' => 'El nombre de la base de datos solo puede llevar letras, números, guiones y «_».',
        ], ['host' => 'servidor', 'port' => 'puerto', 'database' => 'base de datos', 'username' => 'usuario', 'password' => 'contraseña']);

        $result = $probe->test($data['host'], (int) $data['port'], $data['database'], $data['username'], (string) ($data['password'] ?? ''));
        if (! $result['ok']) {
            return back()->withInput($request->except('password'))->withErrors(['database' => $result['message']]);
        }

        $redis = $probe->redis('redis');
        $this->env->set([
            'DB_CONNECTION' => 'mariadb',
            'DB_HOST' => $data['host'],
            'DB_PORT' => (string) $data['port'],
            'DB_DATABASE' => $data['database'],
            'DB_USERNAME' => $data['username'],
            'DB_PASSWORD' => (string) ($data['password'] ?? ''),
            'CACHE_STORE' => $redis ? 'redis' : 'database',
            'SESSION_DRIVER' => $redis ? 'redis' : 'database',
            'REDIS_HOST' => 'redis',
        ]);

        return redirect('/instalar/administrador')->with('install.db', $result['message'].($redis ? ' Redis encontrado: se usará para la caché y las sesiones.' : ''));
    }

    public function admin(): View|RedirectResponse
    {
        if ($this->env->get('DB_DATABASE') === '') {
            return redirect('/instalar/base-de-datos');
        }

        return view('install.admin', ['url' => $this->env->get('APP_URL', url('/'))]);
    }

    public function install(Request $request, RunInstall $run): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url:http,https', 'max:190'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ], [], ['url' => 'dirección de la web', 'name' => 'nombre', 'email' => 'email', 'password' => 'contraseña']);

        try {
            $token = $run($data);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['install' => 'La instalación no ha terminado: '.$e->getMessage()]);
        }

        // La página final no puede ir en la sesión: al instalar, la sesión pasa de archivos a la
        // base de datos. Va en un archivo que se borra al verla (una sola vez).
        file_put_contents(self::doneFile(), (string) json_encode(['email' => $data['email'], 'url' => rtrim($data['url'], '/'), 'token' => $token]));

        return redirect('/instalar/listo');
    }

    public function done(): View
    {
        $done = is_file(self::doneFile()) ? json_decode((string) file_get_contents(self::doneFile()), true) : null;
        abort_unless(is_array($done), 404);
        @unlink(self::doneFile());

        return view('install.done', $done + ['root' => base_path()]);
    }

    private static function doneFile(): string
    {
        return storage_path('app/instalado-listo.json');
    }
}
