<?php

namespace App\Modules\Promotions;

use App\Modules\Orders\Contracts\Events\OrderPaid;
use App\Modules\Orders\Contracts\Events\PaidOrderCancelled;
use App\Modules\Promotions\Contracts\Coupons;
use App\Modules\Promotions\Infrastructure\Listeners\RecordRedemption;
use App\Modules\Promotions\Infrastructure\Listeners\ReleaseRedemption;
use App\Modules\Promotions\Infrastructure\Lookup\EloquentCoupons;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Cupones de descuento, como en WooCommerce (ADR 0024).
 */
class PromotionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Coupons::class, EloquentCoupons::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Event::listen(OrderPaid::class, RecordRedemption::class);
        Event::listen(PaidOrderCancelled::class, ReleaseRedemption::class);
    }
}
