<?php

namespace Database\Seeders;

use App\Modules\Identity\Database\Seeders\RolesSeeder;
use App\Modules\Identity\Database\Seeders\SuperadminSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            SuperadminSeeder::class,
        ]);
    }
}
