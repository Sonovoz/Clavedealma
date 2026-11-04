<?php

use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Database\Seeders\SuperadminSeeder;
use App\Modules\Identity\Infrastructure\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

it('crea los cinco roles y es idempotente', function () {
    $this->seed(RolesSeeder::class);

    expect(RoleModel::pluck('name')->sort()->values()->all())
        ->toBe(['artista', 'cliente', 'fabricante', 'gestor_catalogo', 'superadmin']);
});

it('crea el superadministrador con los datos del .env', function () {
    config()->set('clavedealma.superadmin', [
        'name' => 'Admin',
        'email' => 'admin@clavedealma.test',
        'password' => 'una-clave-larga-y-segura',
    ]);

    $this->seed(SuperadminSeeder::class);

    $user = User::where('email', 'admin@clavedealma.test')->sole();
    expect($user->hasRole(Role::Superadmin->value))->toBeTrue();
});

it('no crea superadministrador si falta la contraseña', function () {
    config()->set('clavedealma.superadmin', ['name' => 'Admin', 'email' => 'admin@clavedealma.test', 'password' => null]);

    $this->seed(SuperadminSeeder::class);

    expect(User::count())->toBe(0);
});

it('rechaza contraseñas cortas para el superadministrador', function () {
    $this->artisan('clavedealma:superadmin', ['email' => 'admin@clavedealma.test'])
        ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'corta')
        ->assertFailed();
});
