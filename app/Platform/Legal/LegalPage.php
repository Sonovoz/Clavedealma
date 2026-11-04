<?php

namespace App\Platform\Legal;

use App\Support\Contracts\HasLabel;

enum LegalPage: string implements HasLabel
{
    case Notice = 'aviso-legal';
    case Privacy = 'privacidad';
    case Cookies = 'cookies';
    case Terms = 'condiciones-de-venta';
    case Shipping = 'envios-y-devoluciones';

    public function label(): string
    {
        return match ($this) {
            self::Notice => 'Aviso legal',
            self::Privacy => 'Política de privacidad',
            self::Cookies => 'Política de cookies',
            self::Terms => 'Condiciones de venta',
            self::Shipping => 'Envíos y devoluciones',
        };
    }
}
