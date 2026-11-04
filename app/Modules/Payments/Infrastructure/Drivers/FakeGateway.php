<?php

namespace App\Modules\Payments\Infrastructure\Drivers;

use App\Modules\Payments\Contracts\GatewayEvent;
use App\Modules\Payments\Contracts\InvalidWebhook;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentRequest;
use App\Modules\Payments\Contracts\RefundResult;
use App\Modules\Payments\Contracts\StartedPayment;

/**
 * Pago simulado para probar la compra sin claves de Stripe. La página de pago muestra
 * «Simular pago correcto / rechazado». Nunca se usa en producción (lo impide el proveedor).
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function publicKey(): ?string
    {
        return null;
    }

    public function start(PaymentRequest $request): StartedPayment
    {
        // Misma clave de idempotencia = mismo cobro, como en Stripe.
        return new StartedPayment('fake_'.substr(hash('sha256', $request->idempotencyKey), 0, 24), null);
    }

    public function parseWebhook(string $payload, ?string $signature): GatewayEvent
    {
        throw new InvalidWebhook('El pago simulado no recibe avisos externos.');
    }

    public function fetch(string $providerReference): GatewayEvent
    {
        return new GatewayEvent('sync:'.$providerReference, GatewayEvent::OTHER, $providerReference);
    }

    public function cancel(string $providerReference): void {}

    public function refund(string $providerReference, int $amountCents, string $idempotencyKey): RefundResult
    {
        return new RefundResult(true, 'fake_re_'.substr(hash('sha256', $idempotencyKey), 0, 20));
    }
}
