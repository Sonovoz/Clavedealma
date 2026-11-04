<?php

use App\Modules\Identity\Contracts\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea el rol "Gestor de catálogo" en las instalaciones existentes
 * (las nuevas lo reciben de RolesSeeder). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        RoleModel::findOrCreate(Role::CatalogManager->value, 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        RoleModel::query()->where('name', Role::CatalogManager->value)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
