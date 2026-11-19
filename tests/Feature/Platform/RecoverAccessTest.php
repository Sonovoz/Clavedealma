<?php

use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Platform\Install\EnvFile;
use App\Platform\Security\SecurityEvent;
use App\Platform\Security\SecurityEventType;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->file = sys_get_temp_dir().'/recuperar-'.uniqid().'.env';
    file_put_contents($this->file, "APP_NAME=Clave\n");
    $this->env = new EnvFile($this->file);
    $this->app->instance(EnvFile::class, $this->env);
});

afterEach(fn () => @unlink($this->file));

$clave = 'una-clave-de-recuperacion-larga';

it('sin la clave en el .env, la página no existe', function () {
    $this->get('/recuperar-acceso')->assertNotFound();
    $this->post('/recuperar-acceso', [])->assertNotFound();

    $this->env->set(['RECUPERAR_ACCESO' => 'corta']);
    $this->get('/recuperar-acceso')->assertNotFound();
});

it('con la clave del .env pone una contraseña nueva, quita los dos pasos y la clave se gasta', function () use ($clave) {
    $admin = User::factory()->withRole(Role::Superadmin)->create(['email' => 'ana@clavedealma.es', 'name' => 'Ana']);
    $admin->saveAppAuthenticationSecret('SECRETO');
    $this->env->set(['RECUPERAR_ACCESO' => $clave]);

    $this->get('/recuperar-acceso')->assertOk()->assertSee('Recuperar el acceso al panel');
    $this->post('/recuperar-acceso', ['key' => 'otra-clave-que-no-es-la-buena', 'email' => 'ana@clavedealma.es', 'password' => 'contraseña-nueva-larga', 'password_confirmation' => 'contraseña-nueva-larga'])
        ->assertSessionHasErrors('key');

    $this->post('/recuperar-acceso', ['key' => $clave, 'email' => 'ana@clavedealma.es', 'password' => 'contraseña-nueva-larga', 'password_confirmation' => 'contraseña-nueva-larga'])
        ->assertOk()->assertSee('Acceso recuperado');

    $admin->refresh();
    expect(Hash::check('contraseña-nueva-larga', $admin->password))->toBeTrue()
        ->and($admin->name)->toBe('Ana')
        ->and($admin->hasTwoFactor())->toBeFalse()
        ->and($this->env->get('RECUPERAR_ACCESO'))->toBe('')
        ->and(SecurityEvent::query()->where('type', SecurityEventType::AccessRecovered)->sole()->description)->not->toContain('contraseña-nueva-larga');

    // Gastada aunque se vuelva a escribir la misma en el .env.
    $this->env->set(['RECUPERAR_ACCESO' => $clave]);
    $this->get('/recuperar-acceso')->assertNotFound();
});

it('si el email no existe, crea un superadministrador con él', function () use ($clave) {
    $this->env->set(['RECUPERAR_ACCESO' => $clave]);

    $this->post('/recuperar-acceso', ['key' => $clave, 'email' => 'nuevo@clavedealma.es', 'password' => 'contraseña-nueva-larga', 'password_confirmation' => 'contraseña-nueva-larga'])->assertOk();

    expect(User::query()->where('email', 'nuevo@clavedealma.es')->sole()->isSuperadmin())->toBeTrue();
});
