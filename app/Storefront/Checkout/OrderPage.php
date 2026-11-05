<?php

namespace App\Storefront\Checkout;

use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Contracts\OrderLookup;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Returns\Application\ReturnOptions;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Support\Money;
use Illuminate\Support\Facades\Storage;

/**
 * Datos de la página de un pedido para el cliente: «Pedido recibido» y
 * «Mi cuenta → Ver pedido» enseñan lo mismo (artículos, totales, seguimiento,
 * facturas y devoluciones). Solo lectura.
 */
final class OrderPage
{
    /**
     * Artículos y totales (también los usa «Pagar el pedido»).
     *
     * @return array<string, mixed>
     */
    public static function summary(Order $order): array
    {
        $m = fn (int $c): string => Money::fromCents($c)->format();

        return [
            'order' => $order,
            'items' => $order->lines->map(fn ($l): array => [
                'name' => $l->product_name,
                'option' => $l->label(),
                'store' => $l->store_name,
                'quantity' => $l->quantity,
                'total' => $m($l->total_cents),
                'image' => $l->image_path === null ? null : Storage::disk('public')->url($l->image_path),
            ])->all(),
            // Subtotal antes del cupón, como en WooCommerce; el descuento va en su fila (ADR 0024).
            'subtotal' => $m($order->subtotal_cents + $order->discount_cents),
            'discount' => $order->discount_cents > 0 ? '−'.$m($order->discount_cents) : null,
            'shippingPrice' => $order->shipping_free ? 'Gratis' : $m($order->shipping_cents),
            'total' => $m($order->total_cents),
            'vat' => $m($order->vat_cents),
        ];
    }

    /**
     * Todo lo de la página del pedido.
     *
     * @return array<string, mixed>
     */
    public static function data(Order $order): array
    {
        $summary = app(OrderLookup::class)->find($order->id);
        $options = app(ReturnOptions::class);
        $payment = Payment::query()->where('order_id', $order->id)->where('status', PaymentStatus::Succeeded)->latest('id')->first(['provider', 'method']);

        return self::summary($order) + [
            'paymentMethod' => self::paymentMethod($payment?->provider, $payment?->method),
            // «Actualizaciones del pedido» de WooCommerce: las notas para el cliente (ADR 0025).
            'customerNotes' => $order->events()->where('type', 'customer_note')->latest('id')->get(['note', 'created_at'])
                ->map(fn ($e): array => ['text' => (string) $e->note, 'date' => $e->created_at->timezone('Europe/Madrid')->format('d/m/Y H:i')])->all(),
            'canReturn' => $summary !== null && $options->open($summary),
            'returnDeadline' => $summary === null ? null : $options->deadline($summary)?->format('d/m/Y'),
            'invoices' => Invoice::query()->where('order_id', $order->id)->orderBy('id')->get()
                ->map(fn (Invoice $i): array => ['code' => $i->code, 'label' => $i->type->label(), 'total' => Money::fromCents($i->total_cents)->format(), 'url' => route('checkout.invoice', [$order->token, $i->code])])->all(),
            'returns' => ReturnRequest::query()->where('order_id', $order->id)->latest('id')->get()
                ->map(fn (ReturnRequest $r): array => ['reference' => $r->reference, 'status' => $r->status->customerLabel(), 'amount' => Money::fromCents($r->refund_cents)->format()])->all(),
        ];
    }

    public static function paymentMethod(?string $provider, ?string $method = null): string
    {
        return match (true) {
            $provider === 'fake' => 'Pago simulado (prueba)',
            $provider === null => '—',
            $provider === 'redsys' => $method === 'bizum' ? 'Bizum' : 'Tarjeta de crédito o débito',
            default => 'Tarjeta u otro método (Stripe)',
        };
    }
}
