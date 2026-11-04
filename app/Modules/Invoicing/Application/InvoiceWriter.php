<?php

namespace App\Modules\Invoicing\Application;

use App\Modules\Invoicing\Contracts\AeatStatus;
use App\Modules\Invoicing\Contracts\InvoiceType;
use App\Modules\Invoicing\Contracts\IssuerIncomplete;
use App\Modules\Invoicing\Domain\VatSplit;
use App\Modules\Invoicing\Domain\Verifactu;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Invoicing\Infrastructure\Models\InvoiceIssuer;
use Illuminate\Support\Facades\DB;

/**
 * Emite una factura: número correlativo de su serie y año, datos del emisor, IVA por
 * línea y registro Veri*Factu encadenado con el anterior. Todo en una transacción.
 */
class InvoiceWriter
{
    /**
     * @param  array<string, mixed>  $attributes  order_id, order_reference, rectifies_id, refund_id, reason, customer_*
     * @param  list<array{description: string, quantity: int, unit_price_cents: int, vat_rate_bps: int, total_cents: int}>  $lines
     */
    public function write(InvoiceType $type, array $attributes, array $lines): Invoice
    {
        $issuer = InvoiceIssuer::current();
        if (! $issuer->complete()) {
            throw new IssuerIncomplete('Faltan datos fiscales de Clave de Alma: '.implode(', ', $issuer->missing()).'.');
        }

        $computed = array_map(fn (array $l): array => $l + VatSplit::fromGross($l['total_cents'], $l['vat_rate_bps']), $lines);
        $base = array_sum(array_column($computed, 'base'));
        $vat = array_sum(array_column($computed, 'vat'));
        $total = array_sum(array_column($computed, 'total_cents'));

        return DB::transaction(function () use ($type, $attributes, $computed, $issuer, $base, $vat, $total): Invoice {
            $now = now();
            $year = (int) $now->copy()->timezone('Europe/Madrid')->format('Y');
            $series = $type === InvoiceType::Simplified ? $issuer->simplified_series : $issuer->rectifying_series;

            // Bloqueo del último registro: numeración y huella sin huecos ni duplicados.
            $previous = Invoice::query()->lockForUpdate()->latest('id')->first();
            $number = (int) Invoice::query()->where('series', $series)->where('year', $year)->max('number') + 1;
            $code = sprintf('%s%d-%06d', $series, $year, $number);

            $snapshot = $issuer->snapshot();
            $issueDate = $now->copy()->timezone('Europe/Madrid')->format('d-m-Y');
            $generatedAt = $now->copy()->timezone('Europe/Madrid')->toIso8601String();
            $hash = Verifactu::hash((string) $snapshot['nif'], $code, $issueDate, $type->value, $vat, $total, $previous?->hash, $generatedAt);

            $invoice = Invoice::query()->create($attributes + [
                'series' => $series,
                'year' => $year,
                'number' => $number,
                'code' => $code,
                'type' => $type,
                'issued_at' => $now,
                'issuer' => $snapshot,
                'base_cents' => $base,
                'vat_cents' => $vat,
                'total_cents' => $total,
                'verifactu' => (bool) config('invoicing.verifactu.enabled', false),
                'hash' => $hash,
                'previous_hash' => $previous?->hash,
                'record_generated_at' => $generatedAt,
                'qr_url' => Verifactu::qrUrl((bool) config('invoicing.verifactu.production', false), (string) $snapshot['nif'], $code, $issueDate, $total),
                'aeat_status' => config('invoicing.verifactu.enabled', false) ? AeatStatus::Pending : AeatStatus::Disabled,
            ]);

            foreach ($computed as $l) {
                $invoice->lines()->create([
                    'description' => mb_substr($l['description'], 0, 255),
                    'quantity' => $l['quantity'],
                    'unit_price_cents' => $l['unit_price_cents'],
                    'vat_rate_bps' => $l['vat_rate_bps'],
                    'base_cents' => $l['base'],
                    'vat_cents' => $l['vat'],
                    'total_cents' => $l['total_cents'],
                ]);
            }

            return $invoice;
        });
    }
}
