<?php

namespace App\Modules\Inventory;

use App\Modules\Catalog\Contracts\Events\VariantCreated;
use App\Modules\Inventory\Application\RecordSale;
use App\Modules\Inventory\Contracts\StockAvailability;
use App\Modules\Inventory\Infrastructure\Listeners\CreateStockLevelForVariant;
use App\Modules\Inventory\Infrastructure\Lookup\EloquentStockAvailability;
use App\Modules\Orders\Contracts\Events\OrderPaid;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StockAvailability::class, EloquentStockAvailability::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Event::listen(VariantCreated::class, CreateStockLevelForVariant::class);
        Event::listen(OrderPaid::class, RecordSale::class);
    }
}
