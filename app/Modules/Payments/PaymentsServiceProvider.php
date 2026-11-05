<?php

namespace App\Modules\Payments;

use App\Modules\Orders\Contracts\Events\OrderCancelled;
use App\Modules\Payments\Application\IssueRefund;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\Refunds;
use App\Modules\Payments\Infrastructure\Drivers\FakeGateway;
use App\Modules\Payments\Infrastructure\Drivers\RedsysGateway;
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
        $this->app->bind(Refunds::class, IssueRefund::class);

        $this->app->singleton(PaymentGateway::class, function ($app): PaymentGateway {
            $driver = (string) config('payments.driver', 'fake');

            if ($driver === 'redsys') {
                $redsys = (array) config('payments.redsys');

                return new RedsysGateway(
                    merchantCode: (string) ($redsys['merchant_code'] ?? ''),
                    terminal: (string) ($redsys['terminal'] ?? '1'),
                    secretKey: (string) ($redsys['secret_key'] ?? ''),
                    environment: (string) ($redsys['environment'] ?? 'test'),
                    bizum: (bool) ($redsys['bizum'] ?? false),
                    merchantName: (string) ($redsys['merchant_name'] ?? 'Clave de Alma'),
                    notifyUrl: route('payments.webhook.redsys'),
                );
            }

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

            throw new RuntimeException("Pasarela de pago no válida para este entorno: [{$driver}]. En producción usa PAYMENT_DRIVER=redsys.");
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        Event::listen(OrderCancelled::class, CancelPendingPayments::class);
    }
}
