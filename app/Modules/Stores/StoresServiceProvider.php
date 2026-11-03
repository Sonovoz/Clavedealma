<?php

namespace App\Modules\Stores;

use Illuminate\Support\ServiceProvider;

class StoresServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
