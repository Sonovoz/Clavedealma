<?php

namespace App\Storefront\Seo;

use Illuminate\Support\Str;

/**
 * Reglas de texto para buscadores (docs/seo/README.md): longitudes que Google muestra
 * sin cortar y limpieza de HTML.
 */
final class SeoText
{
    /** Google corta el título hacia los 60 caracteres (≈ 580 px). */
    public const TITLE_MAX = 60;

    public const TITLE_MIN = 30;

    /** Y la descripción hacia los 155-160. */
    public const DESCRIPTION_MAX = 155;

    public const DESCRIPTION_MIN = 70;

    public const SITE = 'Clave de Alma';

    /** Texto plano de un HTML, en una línea. */
    public static function plain(?string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Corta en una palabra entera, sin pasar del máximo, con «…» si hace falta. */
    public static function limit(string $text, int $max): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:-·|').'…';
    }

    /**
     * Título con marca al final mientras quepa: «La esencia · Farruquito | Clave de Alma».
     *
     * @param  list<string>  $parts  de lo más concreto a lo más general
     */
    public static function title(array $parts): string
    {
        $parts = array_values(array_filter(array_map('trim', $parts)));
        $base = implode(' · ', $parts);

        foreach ([$base.' | '.self::SITE, $base] as $candidate) {
            if (mb_strlen($candidate) <= self::TITLE_MAX) {
                return $candidate;
            }
        }

        return self::limit($parts[0] ?? self::SITE, self::TITLE_MAX);
    }

    /** Precio para datos estructurados: «39.90». */
    public static function price(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    public static function words(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    public static function slugWords(string $slug): string
    {
        return Str::of($slug)->replace('-', ' ')->toString();
    }
}
