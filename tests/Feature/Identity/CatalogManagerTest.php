<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Livewire\Livewire;

it('deja al gestor de catálogo trabajar en el catálogo de todas las tiendas', function (string $url, int $status) {
    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create())->get($url)->assertStatus($status);
})->with([
    'productos' => ['/admin/productos', 200],
    'crear producto' => ['/admin/productos/create', 200],
    'colecciones' => ['/admin/colecciones', 200],
    'prendas base' => ['/admin/prendas-base', 200],
    'importar' => ['/admin/importar-articulos', 200],
    'artistas (datos fiscales)' => ['/admin/artistas', 403],
    'tiendas' => ['/admin/tiendas', 403],
    'usuarios' => ['/admin/usuarios', 403],
    'sistema' => ['/admin/sistema', 403],
]);

it('no deja al fabricante ni al artista tocar el catálogo', function (Role $role) {
    $this->actingAs(User::factory()->withRole($role)->create());

    $this->get('/admin/productos')->assertForbidden();
    $this->get('/admin/importar-articulos')->assertForbidden();
})->with([Role::Manufacturer, Role::Artist]);

it('el superadministrador crea cuentas internas con su rol', function () {
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'Ana', 'email' => 'ana@clavedealma.test', 'role' => Role::CatalogManager->value, 'password' => 'una-clave-larga-1'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'ana@clavedealma.test')->sole()->hasRole(Role::CatalogManager->value))->toBeTrue();
});
