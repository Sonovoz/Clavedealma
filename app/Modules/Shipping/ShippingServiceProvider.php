<?php

namespace App\Modules\Shipping;

use Illuminate\Support\ServiceProvider;

/**
 * Envíos: transportistas, destinos y baremo por peso. Clave de Alma elige el
 * transportista de cada destino; el cliente paga el envío según el peso del pedido.
 */
class ShippingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
