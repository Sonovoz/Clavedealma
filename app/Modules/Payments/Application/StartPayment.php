<?php

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentRequest;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Contracts\StartedPayment;
use App\Modules\Payments\Infrastructure\Models\Payment;

/**
 * Prepara el cobro de un pedido. Con Stripe, recargar la página de pago reutiliza el mismo
 * cobro (clave de idempotencia por pedido e importe). Con Redsys cada visita a su página es
 * un número de pedido nuevo, pero solo se cobra cuando el cliente paga allí (ADR 0026).
 */
class StartPayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function __invoke(int $orderId, string $reference, int $amountCents, string $currency, string $email, ?string $method = null, ?string $returnUrl = null, ?string $cancelUrl = null): StartedPayment
    {
        $methods = $this->gateway->methods();
        $method = $methods === [] ? null : (isset($methods[(string) $method]) ? $method : array_key_first($methods));

        $started = $this->gateway->start(new PaymentRequest(
            orderId: $orderId,
            reference: $reference,
            amountCents: $amountCents,
            currency: $currency,
            email: $email,
            idempotencyKey: "cda-order-{$orderId}-{$amountCents}",
            returnUrl: $returnUrl,
            cancelUrl: $cancelUrl,
            method: $method,
        ));

        Payment::query()->firstOrCreate(
            ['provider_reference' => $started->providerReference],
            ['order_id' => $orderId, 'provider' => $this->gateway->name(), 'method' => $method, 'amount_cents' => $amountCents, 'currency' => $currency, 'status' => PaymentStatus::Pending],
        );

        return $started;
    }
}
