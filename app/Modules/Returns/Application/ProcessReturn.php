<?php

namespace App\Modules\Returns\Application;

use App\Modules\Orders\Contracts\OrderLookup;
use App\Modules\Orders\Contracts\OrderSummary;
use App\Modules\Payments\Contracts\Refunds;
use App\Modules\Returns\Contracts\Events\ReturnReceived;
use App\Modules\Returns\Contracts\ReturnStatus;
use App\Modules\Returns\Infrastructure\Mail\ReturnMailer;
use App\Modules\Returns\Infrastructure\Mail\ReturnUpdateMail;
use App\Modules\Returns\Infrastructure\Models\ReturnLine;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Lo que hace el equipo con una devolución: aprobar (con instrucciones), rechazar, y al
 * recibir las piezas, reponerlas y devolver el dinero (en los 14 días que marca la ley).
 */
class ProcessReturn
{
    public function __construct(private readonly OrderLookup $orders, private readonly Refunds $refunds, private readonly ReturnMailer $mailer) {}

    public function approve(ReturnRequest $return, string $instructions, ?string $by = null): ReturnRequest
    {
        $instructions = trim($instructions);
        if ($instructions === '') {
            throw new InvalidArgumentException('Escribe al cliente cómo enviarnos las piezas.');
        }
        $return = $this->move($return, ReturnStatus::Requested, ReturnStatus::Approved, ['instructions' => $instructions, 'approved_at' => now()]);
        $this->orders->note($return->order_id, 'return_approved', $return->reference.($by ? ' · por '.$by : ''));
        $this->mailer->customer($return, $this->order($return), ReturnUpdateMail::APPROVED);

        return $return;
    }

    public function reject(ReturnRequest $return, string $reason, ?string $by = null): ReturnRequest
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Escribe el motivo (lo leerá el cliente).');
        }
        $return = $this->move($return, ReturnStatus::Requested, ReturnStatus::Rejected, ['rejection_reason' => $reason, 'rejected_at' => now()]);
        $this->orders->note($return->order_id, 'return_rejected', $return->reference.' · '.$reason.($by ? ' · por '.$by : ''));
        $this->mailer->customer($return, $this->order($return), ReturnUpdateMail::REJECTED);

        return $return;
    }

    /**
     * Piezas recibidas: se reponen (si están bien) y se devuelve el dinero. Si la pasarela
     * falla, queda «recibida» con el error y se puede reintentar.
     */
    public function receiveAndRefund(ReturnRequest $return, bool $restock, ?string $by = null): ReturnRequest
    {
        $return = $this->move($return, ReturnStatus::Approved, ReturnStatus::Received, ['received_at' => now(), 'restocked' => $restock]);
        $this->orders->note($return->order_id, 'return_received', $return->reference.($restock ? ' · repuesta en stock' : '').($by ? ' · por '.$by : ''));
        ReturnReceived::dispatch($return->id, (string) $return->reference, $return->lines
            ->map(fn (ReturnLine $l): array => ['variant_id' => $l->product_variant_id, 'quantity' => $l->quantity])->values()->all(), $restock);

        return $this->refund($return);
    }

    public function refund(ReturnRequest $return): ReturnRequest
    {
        if ($return->status !== ReturnStatus::Received) {
            throw new InvalidArgumentException('Solo se devuelve el dinero de una devolución recibida.');
        }

        $result = $this->refunds->refund($return->order_id, $return->refund_cents, 'cda-return-'.$return->id, 'Devolución '.$return->reference);
        if (! $result->succeeded) {
            $return->update(['refund_error' => mb_substr((string) $result->message, 0, 500)]);
            $this->orders->note($return->order_id, 'refund_failed', $return->reference.' · '.$result->message);

            throw new InvalidArgumentException('Piezas recibidas, pero el dinero no se ha podido devolver: '.$result->message);
        }

        $return->update(['status' => ReturnStatus::Refunded, 'refunded_at' => now(), 'refund_error' => null]);
        $this->orders->note($return->order_id, 'refunded', $return->reference.' · '.Money::fromCents($return->refund_cents)->format());
        $this->mailer->customer($return, $this->order($return), ReturnUpdateMail::REFUNDED);

        return $return;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function move(ReturnRequest $return, ReturnStatus $from, ReturnStatus $to, array $changes): ReturnRequest
    {
        return DB::transaction(function () use ($return, $from, $to, $changes): ReturnRequest {
            $fresh = ReturnRequest::query()->with('lines')->lockForUpdate()->findOrFail($return->id);
            if ($fresh->status !== $from) {
                throw new InvalidArgumentException("La devolución está «{$fresh->status->label()}».");
            }
            $fresh->update(['status' => $to] + $changes);

            return $fresh;
        });
    }

    private function order(ReturnRequest $return): OrderSummary
    {
        return $this->orders->find($return->order_id) ?? throw new InvalidArgumentException('El pedido de esta devolución no existe.');
    }
}
