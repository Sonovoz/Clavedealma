<?php

namespace App\Modules\Shipping;

use App\Modules\Shipping\Application\ShippingQuoterService;
use App\Modules\Shipping\Contracts\ShippingQuoter;
use Illuminate\Support\ServiceProvider;

/**
 * Envíos: transportistas, destinos y baremo por peso. Clave de Alma elige el
 * transportista de cada destino; el cliente paga el envío según el peso del pedido.
 */
class ShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ShippingQuoter::class, ShippingQuoterService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
