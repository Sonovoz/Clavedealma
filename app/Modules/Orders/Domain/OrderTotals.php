<?php

namespace App\Modules\Orders\Domain;

use App\Support\Money;

/**
 * Importes de un pedido con IVA incluido (céntimos). El IVA se desglosa por línea y
 * del envío, cada uno con su tipo, y se suma: así cuadra con la factura (fase 6).
 */
final class OrderTotals
{
    /**
     * @param  list<array{total_cents: int, vat_rate_bps: int}>  $lines
     * @return array{subtotal_cents: int, total_cents: int, vat_cents: int}
     */
    public static function compute(array $lines, int $shippingCents, int $shippingVatBps): array
    {
        $subtotal = 0;
        $vat = 0;
        foreach ($lines as $line) {
            $subtotal += $line['total_cents'];
            $vat += $line['total_cents'] - Money::fromCents($line['total_cents'])->withoutVat($line['vat_rate_bps'])->cents;
        }
        $vat += $shippingCents - Money::fromCents($shippingCents)->withoutVat($shippingVatBps)->cents;

        return ['subtotal_cents' => $subtotal, 'total_cents' => $subtotal + $shippingCents, 'vat_cents' => $vat];
    }

    /** Referencia que ve el cliente: CDA-26-000123. */
    public static function reference(int $id, int $year): string
    {
        return sprintf('CDA-%02d-%06d', $year % 100, $id);
    }
}
