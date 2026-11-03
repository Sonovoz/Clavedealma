<?php

namespace App\Modules\Stores\Contracts;

use App\Support\Contracts\HasLabel;

/**
 * Forma jurídica del artista, relevante para la autofacturación
 * (IVA y retención de IRPF los valida el gestor fiscal).
 */
enum TaxRegime: string implements HasLabel
{
    case SelfEmployed = 'autonomo';
    case Company = 'sociedad';

    public function label(): string
    {
        return match ($this) {
            self::SelfEmployed => 'Autónomo',
            self::Company => 'Sociedad',
        };
    }
}
