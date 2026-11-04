<?php

namespace App\Modules\Orders;

use App\Modules\Inventory\Contracts\Events\StockShortage;
use App\Modules\Orders\Application\MarkOrderPaid;
use App\Modules\Orders\Contracts\Events\OrderPaid;
use App\Modules\Orders\Contracts\Events\OrderShipped;
use App\Modules\Orders\Contracts\Events\PaidOrderCancelled;
use App\Modules\Orders\Contracts\OrderLookup;
use App\Modules\Orders\Infrastructure\Console\CancelExpiredOrdersCommand;
use App\Modules\Orders\Infrastructure\Listeners\FlagStockShortage;
use App\Modules\Orders\Infrastructure\Listeners\RecordPaymentFailure;
use App\Modules\Orders\Infrastructure\Listeners\SendCustomerUpdates;
use App\Modules\Orders\Infrastructure\Listeners\SendOrderEmails;
use App\Modules\Orders\Infrastructure\Lookup\EloquentOrderLookup;
use App\Modules\Payments\Contracts\Events\PaymentFailed;
use App\Modules\Payments\Contracts\Events\PaymentSucceeded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Pedidos: uno por compra, con artículos de cualquier tienda. Inmutables (ADR 0014).
 */
class OrdersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OrderLookup::class, EloquentOrderLookup::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Event::listen(PaymentSucceeded::class, MarkOrderPaid::class);
        Event::listen(PaymentFailed::class, RecordPaymentFailure::class);
        Event::listen(StockShortage::class, FlagStockShortage::class);
        // Después de RecordSale (Inventario), para que el aviso incluya un posible faltante.
        Event::listen(OrderPaid::class, SendOrderEmails::class);
        Event::listen(OrderShipped::class, [SendCustomerUpdates::class, 'shipped']);
        Event::listen(PaidOrderCancelled::class, [SendCustomerUpdates::class, 'cancelled']);

        if ($this->app->runningInConsole()) {
            $this->commands([CancelExpiredOrdersCommand::class]);
        }
    }
}
