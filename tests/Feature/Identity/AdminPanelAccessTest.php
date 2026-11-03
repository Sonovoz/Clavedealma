<?php

use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;

it('redirige al login a quien no ha iniciado sesión', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('deja entrar al panel a superadmin, fabricante y artista', function (Role $role) {
    $user = User::factory()->withRole($role)->create();

    $this->actingAs($user)->get('/admin')->assertOk();
})->with([Role::Superadmin, Role::Manufacturer, Role::Artist]);

it('no deja entrar al panel a un cliente', function () {
    $user = User::factory()->withRole(Role::Customer)->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

it('no deja entrar al panel a un usuario sin rol', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('muestra la página Sistema solo al superadministrador', function (Role $role, int $status) {
    $user = User::factory()->withRole($role)->create();

    $this->actingAs($user)->get('/admin/sistema')->assertStatus($status);
})->with([
    'superadmin' => [Role::Superadmin, 200],
    'fabricante' => [Role::Manufacturer, 403],
    'artista' => [Role::Artist, 403],
]);
