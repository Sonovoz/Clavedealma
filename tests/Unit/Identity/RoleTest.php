<?php

use App\Modules\Identity\Contracts\Role;

it('deja entrar al panel a todos los roles menos al cliente', function () {
    expect(Role::panelRoles())->toBe([Role::Superadmin, Role::CatalogManager, Role::Manufacturer, Role::Artist])
        ->and(Role::Customer->canAccessAdminPanel())->toBeFalse();
});

it('guarda los roles con nombres en español', function () {
    expect(array_map(fn (Role $r) => $r->value, Role::cases()))
        ->toBe(['superadmin', 'gestor_catalogo', 'fabricante', 'artista', 'cliente']);
});
