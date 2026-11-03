<?php

namespace App\Modules\Identity\Database\Seeders;

use App\Modules\Identity\Application\CreateSuperadmin;
use Illuminate\Database\Seeder;

/**
 * Crea el superadministrador inicial con los datos SUPERADMIN_* del .env.
 * Si no hay email o contraseña configurados, no hace nada.
 */
class SuperadminSeeder extends Seeder
{
    public function run(CreateSuperadmin $createSuperadmin): void
    {
        /** @var array{name: string, email: ?string, password: ?string} $config */
        $config = config('clavedealma.superadmin');

        if (blank($config['email']) || blank($config['password'])) {
            $this->command?->warn('SUPERADMIN_EMAIL o SUPERADMIN_PASSWORD vacíos: no se crea el superadministrador.');

            return;
        }

        $createSuperadmin($config['name'], $config['email'], $config['password']);
    }
}
