<?php

namespace App\Modules\Inventory;

use App\Modules\Catalog\Contracts\Events\VariantCreated;
use App\Modules\Inventory\Infrastructure\Listeners\CreateStockLevelForVariant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Event::listen(VariantCreated::class, CreateStockLevelForVariant::class);
    }
}
