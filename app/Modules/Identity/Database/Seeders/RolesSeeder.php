<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Contracts\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los roles del sistema. Es idempotente: se puede ejecutar varias veces.
 */
class RolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
