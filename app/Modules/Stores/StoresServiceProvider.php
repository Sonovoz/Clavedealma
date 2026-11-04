<?php

namespace App\Modules\Stores;

use App\Modules\Stores\Contracts\StoreDirectory;
use App\Modules\Stores\Infrastructure\Lookup\EloquentStoreDirectory;
use Illuminate\Support\ServiceProvider;

class StoresServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StoreDirectory::class, EloquentStoreDirectory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
