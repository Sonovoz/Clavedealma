<?php

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Contracts\Events\PaymentFailed;
use App\Modules\Payments\Contracts\Events\PaymentSucceeded;
use App\Modules\Payments\Contracts\GatewayEvent;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\WebhookEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Aplica un aviso de la pasarela una sola vez (registro de avisos con clave única).
 * La fuente de verdad del pago es la pasarela, nunca el navegador del cliente.
 */
class HandleGatewayEvent
{
    public function __invoke(string $provider, GatewayEvent $event): void
    {
        if ($event->type === GatewayEvent::OTHER || $event->providerReference === null) {
            return;
        }

        try {
            DB::transaction(function () use ($provider, $event): void {
                WebhookEvent::query()->create(['provider' => $provider, 'event_id' => $event->eventId, 'type' => $event->type]);

                $payment = Payment::query()->where('provider', $provider)->where('provider_reference', $event->providerReference)->lockForUpdate()->first();
                if ($payment === null) {
                    return;
                }

                if ($event->type === GatewayEvent::SUCCEEDED) {
                    if ($payment->status !== PaymentStatus::Succeeded) {
                        $payment->update(['status' => PaymentStatus::Succeeded, 'succeeded_at' => now(), 'failure_message' => null]);
                    }
                    PaymentSucceeded::dispatch($payment->order_id, $event->amountCents ?? $payment->amount_cents, $event->currency ?? $payment->currency, $provider, (string) $payment->provider_reference);

                    return;
                }

                if ($payment->status !== PaymentStatus::Succeeded) {
                    $payment->update(['status' => PaymentStatus::Failed, 'failure_message' => $event->failureMessage === null ? null : mb_substr($event->failureMessage, 0, 500)]);
                    PaymentFailed::dispatch($payment->order_id, $event->failureMessage);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Aviso repetido: ya se aplicó.
        }
    }
}
