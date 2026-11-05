<?php

namespace App\Modules\Inventory;

use App\Modules\Inventory\Application\RecordSale;
use App\Modules\Inventory\Application\RestockCancelledSale;
use App\Modules\Inventory\Application\RestockReturn;
use App\Modules\Inventory\Contracts\StockAvailability;
use App\Modules\Inventory\Infrastructure\Lookup\EloquentStockAvailability;
use App\Modules\Orders\Contracts\Events\OrderPaid;
use App\Modules\Orders\Contracts\Events\PaidOrderCancelled;
use App\Modules\Returns\Contracts\Events\ReturnReceived;
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

        Event::listen(OrderPaid::class, RecordSale::class);
        Event::listen(PaidOrderCancelled::class, RestockCancelledSale::class);
        Event::listen(ReturnReceived::class, RestockReturn::class);
    }
}
