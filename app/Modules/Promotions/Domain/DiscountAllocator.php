<?php

namespace App\Modules\Promotions\Domain;

/**
 * Reparte un descuento entre líneas en proporción a su importe, en céntimos enteros y
 * sin pasarse del importe de ninguna línea (método del mayor resto). Así el IVA, la
 * factura y la comisión del artista salen de lo que de verdad se cobra (ADR 0024).
 */
final class DiscountAllocator
{
    /**
     * @param  array<int, int>  $bases  clave → importe de la línea
     * @return array<int, int> clave → descuento
     */
    public static function allocate(int $amount, array $bases): array
    {
        $bases = array_filter($bases, fn (int $b): bool => $b > 0);
        $total = array_sum($bases);
        $amount = max(0, min($amount, $total));
        if ($amount === 0 || $total === 0) {
            return array_map(fn (): int => 0, $bases);
        }

        $shares = [];
        $remainders = [];
        foreach ($bases as $key => $base) {
            $exact = $amount * $base;
            $shares[$key] = intdiv($exact, $total);
            $remainders[$key] = $exact % $total;
        }

        $left = $amount - array_sum($shares);
        arsort($remainders);
        foreach (array_keys($remainders) as $key) {
            if ($left === 0) {
                break;
            }
            if ($shares[$key] < $bases[$key]) {
                $shares[$key]++;
                $left--;
            }
        }

        return $shares;
    }

    /** Descuento porcentual en puntos básicos (1500 = 15 %), redondeado al céntimo. */
    public static function percent(int $cents, int $bps): int
    {
        return intdiv($cents * $bps + 5000, 10000);
    }
}
