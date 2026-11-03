<?php

namespace App\Modules\Stores\Contracts;

use App\Support\Contracts\HasLabel;

enum StoreStatus: string implements HasLabel
{
    /** En preparación: no visible para nadie fuera del panel. */
    case Draft = 'draft';
    /** Lista pero oculta: accesible solo con enlace de vista previa (fase 3). */
    case Hidden = 'hidden';
    /** Pública. */
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'En preparación',
            self::Hidden => 'Oculta',
            self::Published => 'Publicada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Hidden => 'warning',
            self::Published => 'success',
        };
    }
}
