<?php

namespace App\Modules\Payments\Infrastructure\Drivers;

use App\Modules\Payments\Contracts\GatewayEvent;
use App\Modules\Payments\Contracts\InvalidWebhook;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentRequest;
use App\Modules\Payments\Contracts\PaymentUnavailable;
use App\Modules\Payments\Contracts\RefundResult;
use App\Modules\Payments\Contracts\StartedPayment;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe con Payment Element (tarjeta, Apple/Google Pay y los métodos activados en el
 * panel de Stripe). La tarjeta la recoge Stripe en el navegador: PCI SAQ A.
 */
class StripeGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $secret,
        private readonly ?string $publishable,
        private readonly ?string $webhookSecret,
    ) {}

    public function name(): string
    {
        return 'stripe';
    }

    public function publicKey(): ?string
    {
        return $this->publishable;
    }

    public function start(PaymentRequest $request): StartedPayment
    {
        if ($this->secret === '') {
            throw new PaymentUnavailable('Faltan las claves de Stripe (STRIPE_KEY y STRIPE_SECRET en el .env).');
        }

        try {
            $intent = $this->client()->paymentIntents->create([
                'amount' => $request->amountCents,
                'currency' => strtolower($request->currency),
                'automatic_payment_methods' => ['enabled' => true],
                'receipt_email' => $request->email,
                'description' => 'Pedido '.$request->reference.' · Clave de Alma',
                'metadata' => ['order_id' => (string) $request->orderId, 'reference' => $request->reference],
            ], ['idempotency_key' => $request->idempotencyKey]);
        } catch (ApiErrorException $e) {
            throw new PaymentUnavailable('Stripe no ha respondido: '.$e->getMessage(), previous: $e);
        }

        return new StartedPayment($intent->id, $intent->client_secret);
    }

    public function parseWebhook(string $payload, ?string $signature): GatewayEvent
    {
        if ($this->webhookSecret === null || $this->webhookSecret === '' || $signature === null) {
            throw new InvalidWebhook('Falta la firma del aviso o STRIPE_WEBHOOK_SECRET.');
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret);
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw new InvalidWebhook($e->getMessage(), previous: $e);
        }

        $object = $event->data->object;
        if (! $object instanceof PaymentIntent) {
            return new GatewayEvent($event->id, GatewayEvent::OTHER, null);
        }

        return match ($event->type) {
            'payment_intent.succeeded' => $this->translate($event->id, $object),
            'payment_intent.payment_failed' => $this->translate($event->id, $object),
            default => new GatewayEvent($event->id, GatewayEvent::OTHER, $object->id),
        };
    }

    public function fetch(string $providerReference): GatewayEvent
    {
        try {
            $intent = $this->client()->paymentIntents->retrieve($providerReference);
        } catch (ApiErrorException $e) {
            throw new PaymentUnavailable('Stripe no ha respondido: '.$e->getMessage(), previous: $e);
        }

        return $this->translate('sync:'.$intent->id.':'.$intent->status, $intent);
    }

    public function cancel(string $providerReference): void
    {
        try {
            $this->client()->paymentIntents->cancel($providerReference);
        } catch (ApiErrorException) {
            // Ya cobrado, ya anulado o no existe: no hay nada que hacer.
        }
    }

    public function refund(string $providerReference, int $amountCents, string $idempotencyKey): RefundResult
    {
        try {
            $refund = $this->client()->refunds->create(
                ['payment_intent' => $providerReference, 'amount' => $amountCents],
                ['idempotency_key' => $idempotencyKey],
            );
        } catch (ApiErrorException $e) {
            return new RefundResult(false, null, 'Stripe no ha hecho la devolución: '.$e->getMessage());
        }

        // «pending» es normal en algunos métodos: Stripe la completa sola.
        return in_array($refund->status, ['succeeded', 'pending'], true)
            ? new RefundResult(true, $refund->id)
            : new RefundResult(false, $refund->id, 'Stripe ha rechazado la devolución ('.$refund->status.').');
    }

    private function translate(string $eventId, PaymentIntent $intent): GatewayEvent
    {
        return match ($intent->status) {
            PaymentIntent::STATUS_SUCCEEDED => new GatewayEvent($eventId, GatewayEvent::SUCCEEDED, $intent->id, $intent->amount_received, strtoupper($intent->currency)),
            PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD => $intent->last_payment_error === null
                ? new GatewayEvent($eventId, GatewayEvent::OTHER, $intent->id)
                : new GatewayEvent($eventId, GatewayEvent::FAILED, $intent->id, failureMessage: $intent->last_payment_error->message ?? 'Pago rechazado'),
            default => new GatewayEvent($eventId, GatewayEvent::OTHER, $intent->id),
        };
    }

    private function client(): StripeClient
    {
        return new StripeClient(['api_key' => $this->secret]);
    }
}
