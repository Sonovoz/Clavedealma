<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Contracts\VariantLookup;
use App\Modules\Catalog\Infrastructure\Lookup\EloquentVariantLookup;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VariantLookup::class, EloquentVariantLookup::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
