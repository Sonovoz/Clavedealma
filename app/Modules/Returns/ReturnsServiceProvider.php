<?php

namespace App\Modules\Returns;

use Illuminate\Support\ServiceProvider;

/**
 * Devoluciones de piezas (derecho de desistimiento, 14 días). ADR 0016.
 */
class ReturnsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
