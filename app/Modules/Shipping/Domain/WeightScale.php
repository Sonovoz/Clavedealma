<?php

namespace App\Modules\Shipping\Domain;

/**
 * Baremo por peso: tramos «hasta X gramos → precio». Se aplica el primer tramo
 * que cubre el peso del paquete; si ninguno lo cubre, no hay tarifa.
 */
final class WeightScale
{
    /**
     * @param  list<array{max_grams: int, price_cents: int}>  $bands
     */
    public static function price(array $bands, int $grams): ?int
    {
        usort($bands, fn (array $a, array $b): int => $a['max_grams'] <=> $b['max_grams']);

        foreach ($bands as $band) {
            if ($grams <= $band['max_grams']) {
                return $band['price_cents'];
            }
        }

        return null;
    }
}
