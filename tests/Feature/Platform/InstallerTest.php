<?php

use App\Filament\Pages\System;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Install\DatabaseProbe;
use App\Platform\Install\EnvFile;
use App\Platform\Install\InstallState;
use App\Platform\Install\RunInstall;
use App\Platform\Settings\Settings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/instalador-'.uniqid();
    mkdir($this->dir);
    copy(base_path('.env.servidor.example'), $this->dir.'/.env');
    $this->env = new EnvFile($this->dir.'/.env');
    $this->app->instance(EnvFile::class, $this->env);
});

afterEach(function () {
    foreach (array_diff(scandir($this->dir) ?: [], ['.', '..']) as $file) {
        unlink($this->dir.'/'.$file);
    }
    rmdir($this->dir);
});

/** La web «sin instalar»: solo cuenta la marca (en las pruebas la base de datos ya existe). */
function sinInstalar(string $dir): void
{
    app()->instance(InstallState::class, new class($dir.'/instalado') extends InstallState
    {
        public function installed(): bool
        {
            return is_file($this->marker);
        }
    });
}

function probe(bool $ok, string $message = 'Conexión correcta con 10.11.9-MariaDB.'): void
{
    app()->instance(DatabaseProbe::class, new class($ok, $message) extends DatabaseProbe
    {
        public function __construct(private bool $ok, private string $message) {}

        public function test(string $host, int $port, string $database, string $username, string $password): array
        {
            return ['ok' => $this->ok, 'message' => $this->message, 'version' => '10.11.9-MariaDB'];
        }

        public function redis(string $host, int $port = 6379): bool
        {
            return true;
        }
    });
}

it('sin instalar, todo lleva al instalador, que empieza comprobando el servidor', function () {
    sinInstalar($this->dir);

    $this->get('/')->assertRedirect('/instalar');
    $this->get('/admin/login')->assertRedirect('/instalar');
    $this->get('/up')->assertOk();
    $this->get('/instalar')->assertOk()->assertSee('Bienvenido a la instalación')
        ->assertSee('PHP 8.3 o superior')->assertSee('Extensión pdo_mysql')->assertSee('Cifrado 3DES');
});

it('la base de datos se prueba antes de guardarla, y se explica el fallo', function () {
    sinInstalar($this->dir);
    probe(false, 'El usuario «web» o la contraseña no son correctos.');
    $form = ['host' => 'mariadb', 'port' => 3306, 'database' => 'clavedealma', 'username' => 'web', 'password' => 'Una #clave "rara" $1'];

    $this->from('/instalar/base-de-datos')->post('/instalar/base-de-datos', $form)
        ->assertRedirect('/instalar/base-de-datos')->assertSessionHasErrors(['database' => 'El usuario «web» o la contraseña no son correctos.']);
    expect($this->env->get('DB_DATABASE'))->toBe('');

    probe(true);
    $this->post('/instalar/base-de-datos', $form)->assertRedirect('/instalar/administrador');

    expect($this->env->read())->toMatchArray([
        'DB_CONNECTION' => 'mariadb', 'DB_HOST' => 'mariadb', 'DB_PORT' => '3306', 'DB_DATABASE' => 'clavedealma',
        'DB_USERNAME' => 'web', 'DB_PASSWORD' => 'Una #clave "rara" $1', 'CACHE_STORE' => 'redis', 'SESSION_DRIVER' => 'redis',
    ]);
    $this->get('/instalar/administrador')->assertOk()->assertSee('Sitio y administrador');
});

it('al instalar enseña el cron y luego el instalador deja de existir', function () {
    sinInstalar($this->dir);
    $this->env->set(['DB_DATABASE' => 'clavedealma']);
    $marker = $this->dir.'/instalado';
    app()->instance(RunInstall::class, new class($marker) extends RunInstall
    {
        public function __construct(private string $marker) {}

        public function __invoke(array $data): string
        {
            touch($this->marker);

            return str_repeat('a', 40);
        }
    });

    $this->post('/instalar/administrador', ['url' => 'https://clavedealma.es', 'name' => 'Ana', 'email' => 'ana@clavedealma.es', 'password' => 'corta', 'password_confirmation' => 'corta'])
        ->assertSessionHasErrors('password');

    $this->post('/instalar/administrador', ['url' => 'https://clavedealma.es', 'name' => 'Ana', 'email' => 'ana@clavedealma.es', 'password' => 'una-clave-bien-larga', 'password_confirmation' => 'una-clave-bien-larga'])
        ->assertRedirect('/instalar/listo');
    $this->get('/instalar/listo')->assertOk()->assertSee('¡Instalado!')->assertSee('ana@clavedealma.es')
        ->assertSee('docker exec php-web php')->assertSee('https://clavedealma.es/cron/'.str_repeat('a', 40));

    $this->get('/instalar')->assertNotFound();
    $this->get('/instalar/base-de-datos')->assertNotFound();
});

it('el último paso crea las tablas, los roles y el superadministrador, y marca la web', function () {
    $this->env->set(['DB_CONNECTION' => 'sqlite']);
    $state = new InstallState($this->dir.'/instalado');
    $this->app->instance(InstallState::class, $state);

    $token = app(RunInstall::class)(['url' => 'https://clavedealma.es/', 'name' => 'Ana', 'email' => 'ana@clavedealma.es', 'password' => 'una-clave-bien-larga']);

    expect(User::query()->where('email', 'ana@clavedealma.es')->sole()->isSuperadmin())->toBeTrue()
        ->and(is_file($this->dir.'/instalado'))->toBeTrue()
        ->and($token)->toHaveLength(40)
        ->and($this->env->read())->toMatchArray(['APP_URL' => 'https://clavedealma.es', 'APP_DOMAIN' => 'clavedealma.es', 'SESSION_SECURE_COOKIE' => 'true', 'CRON_TOKEN' => $token]);
});

it('con la web instalada, el instalador no existe', function () {
    $this->get('/instalar')->assertNotFound();
    $this->get('/instalar/listo')->assertNotFound();
});

it('el .env guarda bien valores con espacios, comillas, # y $', function () {
    $this->env->set(['DB_PASSWORD' => 'a b#c "d" $e\\f', 'NUEVA_CLAVE' => 'x']);

    expect($this->env->get('DB_PASSWORD'))->toBe('a b#c "d" $e\\f')
        ->and($this->env->get('NUEVA_CLAVE'))->toBe('x')
        ->and(substr_count((string) file_get_contents($this->dir.'/.env'), 'DB_PASSWORD='))->toBe(1);
});

it('las tareas programadas se lanzan por la dirección secreta y el panel dice si funcionan', function () {
    $this->get('/cron/'.str_repeat('b', 40))->assertNotFound();

    config()->set('clavedealma.cron_token', str_repeat('b', 40));
    Artisan::shouldReceive('call')->once()->with('schedule:run')->andReturn(0);
    $this->get('/cron/'.str_repeat('c', 40))->assertNotFound();
    $this->get('/cron/'.str_repeat('b', 40))->assertOk()->assertSee('ok');
});

it('«Estado del sistema» avisa si las tareas programadas no corren', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());

    $this->get(System::getUrl())->assertSee('Nunca se han ejecutado');
    Settings::put('schedule.last_run', now()->toIso8601String());
    $this->get(System::getUrl())->assertSee('Funcionan: última vez');
});
