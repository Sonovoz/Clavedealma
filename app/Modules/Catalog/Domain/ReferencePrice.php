<?php

namespace App\Modules\Catalog\Domain;

/**
 * Precio anterior de una rebaja: el más bajo aplicado en los 30 días previos
 * a la bajada (art. 20 de la Ley 7/1996 de Ordenación del Comercio Minorista,
 * directiva Ómnibus). Solo hay rebaja si el precio actual es menor que ese.
 */
final class ReferencePrice
{
    public const DAYS = 30;

    /**
     * @param  list<array{cents: int, since: int}>  $history  precios con su inicio (marca de tiempo), en cualquier orden
     * @return array{cents: int, percent: int}|null precio anterior y descuento en % redondeado, o null si no es rebaja
     */
    public static function discount(array $history): ?array
    {
        if ($history === []) {
            return null;
        }
        usort($history, fn (array $a, array $b): int => $a['since'] <=> $b['since']);

        $current = $history[array_key_last($history)];
        $windowStart = $current['since'] - self::DAYS * 86_400;

        $lowest = null;
        foreach ($history as $i => $entry) {
            $end = $history[$i + 1]['since'] ?? PHP_INT_MAX;
            if ($end <= $windowStart || $entry['since'] >= $current['since']) {
                continue;
            }
            $lowest = $lowest === null ? $entry['cents'] : min($lowest, $entry['cents']);
        }

        if ($lowest === null || $lowest <= $current['cents']) {
            return null;
        }

        return [
            'cents' => $lowest,
            'percent' => intdiv(($lowest - $current['cents']) * 100 * 2 + $lowest, $lowest * 2),
        ];
    }
}
