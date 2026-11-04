<?php

namespace App\Platform\Documents;

use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Invoicing\Infrastructure\Models\InvoiceLine;
use App\Support\Money;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Datos del PDF de una factura: emisor, líneas, desglose de IVA y QR de Veri*Factu.
 */
final class InvoiceDocument
{
    /**
     * @return array<string, mixed>
     */
    public static function data(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines', 'rectifies']);
        $m = fn (int $c): string => Money::fromCents($c)->format();

        $vat = $invoice->lines->groupBy('vat_rate_bps')->map(fn ($ls, $rate): array => [
            'rate' => rtrim(rtrim(number_format(((int) $rate) / 100, 2, ',', ''), '0'), ','),
            'base' => $m((int) $ls->sum('base_cents')),
            'vat' => $m((int) $ls->sum('vat_cents')),
        ])->values()->all();

        $svg = $invoice->verifactu ? (new Writer(new ImageRenderer(new RendererStyle(150, 1), new SvgImageBackEnd)))->writeString($invoice->qr_url) : null;

        return [
            'invoice' => $invoice,
            'issuer' => $invoice->issuer,
            'date' => $invoice->issued_at->copy()->timezone('Europe/Madrid')->format('d/m/Y'),
            'lines' => $invoice->lines->map(fn (InvoiceLine $l): array => [
                'description' => $l->description,
                'quantity' => $l->quantity,
                'unit' => $m($l->unit_price_cents),
                'rate' => rtrim(rtrim(number_format($l->vat_rate_bps / 100, 2, ',', ''), '0'), ',').' %',
                'total' => $m($l->total_cents),
            ])->all(),
            'vatBreakdown' => $vat,
            'base' => $m($invoice->base_cents),
            'vatTotal' => $m($invoice->vat_cents),
            'total' => $m($invoice->total_cents),
            'qr' => $svg === null ? null : 'data:image/svg+xml;base64,'.base64_encode($svg),
        ];
    }
}
