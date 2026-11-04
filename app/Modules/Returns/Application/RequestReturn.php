<?php

namespace App\Modules\Returns\Application;

use App\Modules\Orders\Contracts\OrderLookup;
use App\Modules\Orders\Contracts\OrderSummary;
use App\Modules\Returns\Contracts\ReturnReason;
use App\Modules\Returns\Contracts\ReturnStatus;
use App\Modules\Returns\Domain\ReturnPolicy;
use App\Modules\Returns\Infrastructure\Mail\ReturnMailer;
use App\Modules\Returns\Infrastructure\Mail\ReturnUpdateMail;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * El cliente pide devolver piezas desde la página de su pedido (dentro de plazo).
 * El importe se calcula aquí y queda fijado en la devolución.
 */
class RequestReturn
{
    public function __construct(private readonly ReturnOptions $options, private readonly OrderLookup $orders, private readonly ReturnMailer $mailer) {}

    /**
     * @param  array<int, int>  $quantities  línea del pedido → unidades
     */
    public function __invoke(OrderSummary $order, array $quantities, ReturnReason $reason, ?string $comment): ReturnRequest
    {
        if (! $this->options->open($order)) {
            throw new InvalidArgumentException('Este pedido ya no admite devoluciones.');
        }

        $returnable = $this->options->returnable($order);
        $chosen = array_filter(array_map('intval', $quantities), fn (int $q): bool => $q > 0);
        if ($chosen === []) {
            throw new InvalidArgumentException('Elige al menos una pieza para devolver.');
        }
        foreach ($chosen as $lineId => $qty) {
            if ($order->line((int) $lineId) === null || $qty > ($returnable[$lineId] ?? 0)) {
                throw new InvalidArgumentException('No se pueden devolver esas unidades.');
            }
        }

        $refund = ReturnPolicy::refundCents(
            array_map(fn ($l): array => ['id' => $l->id, 'quantity' => $l->quantity, 'unit_price_cents' => $l->unitPriceCents], $order->lines),
            $this->options->alreadyReturned($order->id),
            $chosen,
            $order->shippingCents,
        );
        $goods = array_sum(array_map(fn ($id, $q): int => $q * (int) $order->line((int) $id)?->unitPriceCents, array_keys($chosen), $chosen));

        $return = DB::transaction(function () use ($order, $chosen, $reason, $comment, $refund, $goods): ReturnRequest {
            $return = ReturnRequest::query()->create([
                'order_id' => $order->id,
                'order_reference' => $order->reference,
                'status' => ReturnStatus::Requested,
                'reason' => $reason,
                'comment' => $comment === null || trim($comment) === '' ? null : mb_substr(trim($comment), 0, 500),
                'refund_cents' => $refund,
                'includes_shipping' => $refund > $goods,
            ]);
            $return->update(['reference' => sprintf('DEV-%02d-%06d', (int) now()->format('y'), $return->id)]);
            foreach ($chosen as $lineId => $qty) {
                $line = $order->line((int) $lineId);
                $return->lines()->create([
                    'order_line_id' => $line?->id, 'product_variant_id' => $line?->variantId, 'sku' => $line?->sku,
                    'product_name' => $line?->productName, 'option' => $line?->option, 'quantity' => $qty, 'unit_price_cents' => $line?->unitPriceCents,
                ]);
            }

            return $return;
        });

        $this->orders->note($order->id, 'return_requested', $return->reference);
        $this->mailer->customer($return, $order, ReturnUpdateMail::REQUESTED);
        $this->mailer->team($return, $order);

        return $return;
    }
}
