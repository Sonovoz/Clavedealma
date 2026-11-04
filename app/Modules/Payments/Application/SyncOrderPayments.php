<?php

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Contracts\PaymentUnavailable;
use App\Modules\Payments\Infrastructure\Models\Payment;

/**
 * Al volver de la pasarela se pregunta el estado del cobro, por si el webhook tarda
 * (o no llega, como en local sin el reenvío de Stripe). Mismo camino que el webhook.
 */
class SyncOrderPayments
{
    public function __construct(private readonly PaymentGateway $gateway, private readonly HandleGatewayEvent $handle) {}

    public function __invoke(int $orderId): void
    {
        $payments = Payment::query()->where('order_id', $orderId)->where('provider', $this->gateway->name())
            ->whereIn('status', [PaymentStatus::Pending, PaymentStatus::Failed])->whereNotNull('provider_reference')->get();

        foreach ($payments as $payment) {
            try {
                ($this->handle)($this->gateway->name(), $this->gateway->fetch((string) $payment->provider_reference));
            } catch (PaymentUnavailable) {
                // Se reintenta en la siguiente visita o llega por webhook.
            }
        }
    }
}
