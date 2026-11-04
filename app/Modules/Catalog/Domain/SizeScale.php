<?php

namespace App\Modules\Catalog\Domain;

/**
 * Escala de tallas común a todas las tiendas: XS, S, M, L, XL, XXL, 3XL.
 * Unifica las variantes de escritura (2XL = XXL) y ordena siempre igual.
 * Se admiten otras tallas (numéricas, infantiles…), que van detrás.
 */
final class SizeScale
{
    public const STANDARD = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'];

    private const ALIASES = ['2XL' => 'XXL', 'XXXL' => '3XL'];

    /** Formas de escribir "talla única": se guarda sin talla. */
    private const ONE_SIZE = ['TU', 'U', 'UNICA', 'TALLA UNICA', 'ONE SIZE', 'OS'];

    public static function normalize(?string $size): ?string
    {
        if ($size === null) {
            return null;
        }
        $size = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $size) ?? ''));
        $plain = strtr($size, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);

        if ($size === '' || in_array($plain, self::ONE_SIZE, true)) {
            return null;
        }

        return self::ALIASES[$size] ?? $size;
    }

    /** Posición para ordenar: primero la escala estándar, después el resto. */
    public static function rank(?string $size): int
    {
        $index = array_search(self::normalize($size), self::STANDARD, true);

        return $index === false ? 100 : $index;
    }

    /**
     * @param  list<?string>  $sizes
     * @return list<?string>
     */
    public static function sort(array $sizes): array
    {
        usort($sizes, fn (?string $a, ?string $b): int => [self::rank($a), (string) $a] <=> [self::rank($b), (string) $b]);

        return $sizes;
    }
}
