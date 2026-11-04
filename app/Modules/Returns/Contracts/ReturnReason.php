<?php

namespace App\Modules\Returns\Contracts;

use App\Support\Contracts\HasLabel;

enum ReturnReason: string implements HasLabel
{
    case Size = 'size';
    case NotAsExpected = 'not_as_expected';
    case Defective = 'defective';
    case WrongItem = 'wrong_item';
    case ChangedMind = 'changed_mind';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Size => 'La talla no me queda bien',
            self::NotAsExpected => 'No es como esperaba',
            self::Defective => 'Ha llegado con un defecto',
            self::WrongItem => 'No es lo que pedí',
            self::ChangedMind => 'He cambiado de opinión',
            self::Other => 'Otro motivo',
        };
    }

    /** Error nuestro: el envío de vuelta lo paga Clave de Alma. */
    public function ourFault(): bool
    {
        return $this === self::Defective || $this === self::WrongItem;
    }
}
