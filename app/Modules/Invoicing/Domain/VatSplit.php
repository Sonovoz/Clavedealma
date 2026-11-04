<?php

namespace App\Modules\Invoicing\Domain;

use App\Support\Money;

/**
 * Cuentas de IVA de una factura con precios IVA incluido (céntimos, puntos básicos).
 */
final class VatSplit
{
    /**
     * @return array{base: int, vat: int}
     */
    public static function fromGross(int $grossCents, int $vatRateBps): array
    {
        $base = Money::fromCents(abs($grossCents))->withoutVat($vatRateBps)->cents;
        $sign = $grossCents < 0 ? -1 : 1;

        return ['base' => $sign * $base, 'vat' => $grossCents - $sign * $base];
    }

    /**
     * Reparte un importe entre los tipos de IVA en proporción a lo facturado con cada uno
     * (método del mayor resto: la suma cuadra al céntimo).
     *
     * @param  array<int, int>  $grossByRate  tipo (bps) → importe IVA incluido
     * @return array<int, int> tipo (bps) → parte del importe
     */
    public static function allocate(int $amountCents, array $grossByRate): array
    {
        $total = array_sum($grossByRate);
        if ($total <= 0 || $grossByRate === []) {
            return [];
        }

        $parts = [];
        $remainders = [];
        foreach ($grossByRate as $rate => $gross) {
            $exact = $amountCents * $gross;
            $parts[$rate] = intdiv($exact, $total);
            $remainders[$rate] = $exact % $total;
        }
        arsort($remainders);
        $left = $amountCents - array_sum($parts);
        foreach (array_keys($remainders) as $rate) {
            if ($left <= 0) {
                break;
            }
            $parts[$rate]++;
            $left--;
        }

        return array_filter($parts, fn (int $p): bool => $p !== 0);
    }
}
