<?php

namespace App\Modules\Invoicing\Application;

use App\Modules\Invoicing\Contracts\InvoiceType;
use App\Modules\Invoicing\Domain\VatSplit;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Contracts\OrderLookup;
use App\Support\Money;

/**
 * Facturas de un pedido: la simplificada al cobrarlo y una rectificativa por cada
 * devolución de dinero. Idempotente. Las compras de prueba no se facturan.
 */
class IssueInvoices
{
    public function __construct(private readonly OrderLookup $orders, private readonly InvoiceWriter $writer, private readonly SendToAeat $aeat) {}

    public function forOrder(int $orderId): ?Invoice
    {
        $existing = Invoice::query()->where('order_id', $orderId)->where('type', InvoiceType::Simplified)->first();
        if ($existing !== null) {
            return $existing;
        }
        $order = $this->orders->find($orderId);
        if ($order === null || $order->isTest || $order->paidAt === null) {
            return null;
        }

        $lines = [];
        foreach ($order->lines as $line) {
            $lines[] = [
                'description' => $line->productName.($line->option !== '' ? ' · '.$line->option : '').' · '.$line->sku.' ('.$line->storeName.')'
                    // Cupón (ADR 0024): el total de la línea ya va descontado; se dice cuánto.
                    .($line->discountCents > 0 ? ' · descuento cupón '.$order->couponCode.' −'.Money::fromCents($line->discountCents)->format() : ''),
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unitPriceCents,
                'vat_rate_bps' => $line->vatRateBps,
                'total_cents' => $line->totalCents,
            ];
        }
        if ($order->shippingCents > 0) {
            $lines[] = ['description' => 'Envío', 'quantity' => 1, 'unit_price_cents' => $order->shippingCents, 'vat_rate_bps' => $order->shippingVatBps, 'total_cents' => $order->shippingCents];
        }

        $invoice = $this->writer->write(InvoiceType::Simplified, [
            'order_id' => $order->id,
            'order_reference' => $order->reference,
            'customer_name' => $order->customerName,
            'customer_email' => $order->email,
        ], $lines);

        $this->orders->note($order->id, 'invoiced', $invoice->code);
        ($this->aeat)($invoice);

        return $invoice;
    }

    /**
     * Rectificativa por el dinero devuelto, repartido entre los tipos de IVA de la factura.
     */
    public function forRefund(int $refundId, int $orderId, int $amountCents, string $reason): ?Invoice
    {
        $existing = Invoice::query()->where('refund_id', $refundId)->first();
        if ($existing !== null) {
            return $existing;
        }
        $original = Invoice::query()->with('lines')->where('order_id', $orderId)->where('type', InvoiceType::Simplified)->first()
            ?? $this->forOrder($orderId);
        if ($original === null) {
            return null; // compra de prueba
        }
        $original->loadMissing('lines');

        $byRate = [];
        foreach ($original->lines as $line) {
            $byRate[$line->vat_rate_bps] = ($byRate[$line->vat_rate_bps] ?? 0) + $line->total_cents;
        }
        $lines = [];
        foreach (VatSplit::allocate($amountCents, $byRate) as $rate => $part) {
            $lines[] = ['description' => 'Rectificación de '.$original->code.': '.$reason, 'quantity' => 1, 'unit_price_cents' => -$part, 'vat_rate_bps' => $rate, 'total_cents' => -$part];
        }

        $invoice = $this->writer->write(InvoiceType::RectifyingSimplified, [
            'order_id' => $orderId,
            'order_reference' => $original->order_reference,
            'rectifies_id' => $original->id,
            'refund_id' => $refundId,
            'reason' => mb_substr($reason, 0, 255),
            'customer_name' => $original->customer_name,
            'customer_email' => $original->customer_email,
        ], $lines);

        $this->orders->note($orderId, 'invoiced', $invoice->code.' (rectificativa)');
        ($this->aeat)($invoice);

        return $invoice;
    }
}
