<?php

namespace App\Modules\Cart\Domain;

/**
 * Contenido de la cesta: variante → unidades. Sin precios (se calculan siempre al leerla).
 * Reglas puras, sin framework.
 */
final class CartLines
{
    /**
     * @param  array<int, int>  $lines
     * @return array<int, int>
     */
    public static function add(array $lines, int $variantId, int $quantity, int $maxPerLine): array
    {
        return self::set($lines, $variantId, ($lines[$variantId] ?? 0) + $quantity, $maxPerLine);
    }

    /**
     * @param  array<int, int>  $lines
     * @return array<int, int>
     */
    public static function set(array $lines, int $variantId, int $quantity, int $maxPerLine): array
    {
        if ($variantId < 1) {
            return $lines;
        }
        if ($quantity < 1) {
            unset($lines[$variantId]);

            return $lines;
        }
        $lines[$variantId] = min($quantity, $maxPerLine);

        return $lines;
    }

    /**
     * Limpia lo que venga de la sesión (claves y cantidades enteras y positivas).
     *
     * @return array<int, int>
     */
    public static function sanitize(mixed $raw, int $maxPerLine): array
    {
        $lines = [];
        foreach (is_array($raw) ? $raw : [] as $id => $qty) {
            if (is_numeric($id) && is_numeric($qty) && (int) $id > 0 && (int) $qty > 0) {
                $lines[(int) $id] = min((int) $qty, $maxPerLine);
            }
        }

        return $lines;
    }
}
