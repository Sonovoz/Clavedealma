<?php

namespace App\Modules\Invoicing\Domain;

/**
 * Registro de facturación Veri*Factu (RD 1007/2023 y Orden HAC/1177/2024).
 * Huella SHA-256 encadenada con el registro anterior y URL del QR de cotejo de la AEAT.
 * El formato debe validarse contra el entorno de pruebas de la AEAT antes de producción.
 */
final class Verifactu
{
    public const QR_PRODUCTION = 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR';

    public const QR_TESTING = 'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQR';

    /** Importe como en el XML de la AEAT: punto decimal y dos decimales. */
    public static function amount(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Huella de un registro de alta.
     *
     * @param  string  $issueDate  dd-mm-aaaa
     * @param  string  $generatedAt  fecha y hora con huso (ISO 8601, p. ej. 2026-11-04T19:20:30+01:00)
     */
    public static function hash(string $issuerNif, string $number, string $issueDate, string $type, int $vatCents, int $totalCents, ?string $previousHash, string $generatedAt): string
    {
        $chain = 'IDEmisorFactura='.trim($issuerNif)
            .'&NumSerieFactura='.trim($number)
            .'&FechaExpedicionFactura='.$issueDate
            .'&TipoFactura='.$type
            .'&CuotaTotal='.self::amount($vatCents)
            .'&ImporteTotal='.self::amount($totalCents)
            .'&Huella='.($previousHash ?? '')
            .'&FechaHoraHusoGenRegistro='.$generatedAt;

        return strtoupper(hash('sha256', $chain));
    }

    public static function qrUrl(bool $production, string $issuerNif, string $number, string $issueDate, int $totalCents): string
    {
        return ($production ? self::QR_PRODUCTION : self::QR_TESTING).'?'.http_build_query([
            'nif' => trim($issuerNif),
            'numserie' => trim($number),
            'fecha' => $issueDate,
            'importe' => self::amount($totalCents),
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
