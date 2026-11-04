<?php

namespace App\Modules\Payments\Application;

use App\Modules\Payments\Contracts\Events\RefundIssued;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Contracts\RefundResult;
use App\Modules\Payments\Contracts\Refunds;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\Refund;
use Illuminate\Support\Facades\DB;

/**
 * Devuelve dinero del cobro de un pedido. Nunca más de lo cobrado menos lo ya devuelto,
 * y la misma clave de idempotencia nunca devuelve dos veces.
 */
class IssueRefund implements Refunds
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    public function refund(int $orderId, int $amountCents, string $idempotencyKey, string $reason): RefundResult
    {
        $done = Refund::query()->where('idempotency_key', $idempotencyKey)->where('status', Refund::SUCCEEDED)->first();
        if ($done !== null) {
            return new RefundResult(true, $done->provider_reference);
        }
        if ($amountCents < 1) {
            return new RefundResult(false, null, 'No hay importe que devolver.');
        }

        return DB::transaction(function () use ($orderId, $amountCents, $idempotencyKey, $reason): RefundResult {
            $payment = Payment::query()->where('order_id', $orderId)->where('status', PaymentStatus::Succeeded)->lockForUpdate()->latest('id')->first();
            if ($payment === null || $payment->provider_reference === null) {
                return new RefundResult(false, null, 'Este pedido no tiene un cobro hecho que devolver.');
            }
            if ($payment->provider !== $this->gateway->name()) {
                return new RefundResult(false, null, "El cobro se hizo con «{$payment->provider}» y la pasarela activa es «{$this->gateway->name()}».");
            }

            $available = $payment->amount_cents - $this->refundedCents($orderId);
            if ($amountCents > $available) {
                return new RefundResult(false, null, 'No se puede devolver más de lo cobrado (quedan '.number_format($available / 100, 2, ',', '.').' €).');
            }

            // Tras un intento fallido, la pasarela repetiría el mismo error con la misma clave.
            $failed = (int) Refund::query()->where('idempotency_key', $idempotencyKey)->value('attempts');
            $result = $this->gateway->refund($payment->provider_reference, $amountCents, $failed === 0 ? $idempotencyKey : $idempotencyKey.':'.$failed);

            $refund = Refund::query()->updateOrCreate(['idempotency_key' => $idempotencyKey], [
                'attempts' => $result->succeeded ? $failed : $failed + 1,
                'payment_id' => $payment->id,
                'order_id' => $orderId,
                'amount_cents' => $amountCents,
                'provider_reference' => $result->providerReference,
                'status' => $result->succeeded ? Refund::SUCCEEDED : Refund::FAILED,
                'reason' => mb_substr($reason, 0, 255),
                'failure_message' => $result->message === null ? null : mb_substr($result->message, 0, 500),
            ]);

            if ($result->succeeded) {
                RefundIssued::dispatch($refund->id, $orderId, $amountCents, mb_substr($reason, 0, 255));
            }

            return $result;
        });
    }

    public function succeeded(): array
    {
        return Refund::query()->where('status', Refund::SUCCEEDED)->orderBy('id')->get()
            ->map(fn (Refund $r): array => ['id' => $r->id, 'order_id' => $r->order_id, 'amount_cents' => $r->amount_cents, 'reason' => $r->reason])->values()->all();
    }

    public function refundedCents(int $orderId): int
    {
        return (int) Refund::query()->where('order_id', $orderId)->where('status', Refund::SUCCEEDED)->sum('amount_cents');
    }
}
