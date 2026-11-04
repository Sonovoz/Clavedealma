<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Contracts\Events\OrderCancelled;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Infrastructure\Drivers\FakeGateway;
use App\Modules\Payments\Infrastructure\Drivers\StripeGateway;
use App\Modules\Payments\Infrastructure\Listeners\CancelPendingPayments;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Pagos: una sola pasarela para todas las tiendas (Clave de Alma es el vendedor).
 */
class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function ($app): PaymentGateway {
            $driver = (string) config('payments.driver', 'fake');

            if ($driver === 'stripe') {
                return new StripeGateway(
                    (string) config('payments.stripe.secret'),
                    config('payments.stripe.key') ?: null,
                    config('payments.stripe.webhook_secret') ?: null,
                );
            }

            if ($driver === 'fake' && ! $app->isProduction()) {
                return new FakeGateway;
            }

            throw new RuntimeException("Pasarela de pago no válida para este entorno: [{$driver}]. En producción usa PAYMENT_DRIVER=stripe.");
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Event::listen(OrderCancelled::class, CancelPendingPayments::class);
    }
}
