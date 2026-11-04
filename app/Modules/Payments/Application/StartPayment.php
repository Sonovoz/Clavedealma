<?php

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentRequest;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Contracts\StartedPayment;
use App\Modules\Payments\Infrastructure\Models\Payment;

/**
 * Prepara el cobro de un pedido. Recargar la página de pago reutiliza el mismo cobro
 * (clave de idempotencia por pedido e importe): nunca se cobra dos veces.
 */
class StartPayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function __invoke(int $orderId, string $reference, int $amountCents, string $currency, string $email): StartedPayment
    {
        $started = $this->gateway->start(new PaymentRequest(
            orderId: $orderId,
            reference: $reference,
            amountCents: $amountCents,
            currency: $currency,
            email: $email,
            idempotencyKey: "cda-order-{$orderId}-{$amountCents}",
        ));

        Payment::query()->firstOrCreate(
            ['provider_reference' => $started->providerReference],
            ['order_id' => $orderId, 'provider' => $this->gateway->name(), 'amount_cents' => $amountCents, 'currency' => $currency, 'status' => PaymentStatus::Pending],
        );

        return $started;
    }
}
