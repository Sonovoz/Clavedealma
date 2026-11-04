<?php

namespace App\Modules\Invoicing\Contracts;

use App\Support\Contracts\HasLabel;

/**
 * Tipos de factura de Veri*Factu que usa Clave de Alma (venta a consumidores).
 */
enum InvoiceType: string implements HasLabel
{
    /** Factura simplificada (art. 4 y 7 del Reglamento de facturación). */
    case Simplified = 'F2';
    /** Rectificativa de una simplificada (devoluciones y cancelaciones). */
    case RectifyingSimplified = 'R5';

    public function label(): string
    {
        return match ($this) {
            self::Simplified => 'Factura simplificada',
            self::RectifyingSimplified => 'Factura rectificativa',
        };
    }
}
