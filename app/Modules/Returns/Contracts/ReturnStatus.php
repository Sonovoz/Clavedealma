<?php

namespace App\Modules\Returns\Contracts;

use App\Support\Contracts\HasLabel;

enum ReturnStatus: string implements HasLabel
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Received = 'received';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Pedida · por revisar',
            self::Approved => 'Aprobada · esperando el paquete',
            self::Rejected => 'Rechazada',
            self::Received => 'Recibida · devolución de dinero pendiente',
            self::Refunded => 'Terminada · dinero devuelto',
        };
    }

    public function customerLabel(): string
    {
        return match ($this) {
            self::Requested => 'La estamos revisando',
            self::Approved => 'Aprobada: envíanos las piezas',
            self::Rejected => 'No aceptada',
            self::Received => 'Hemos recibido las piezas',
            self::Refunded => 'Dinero devuelto',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Requested => 'warning',
            self::Approved => 'info',
            self::Rejected => 'gray',
            self::Received => 'danger',
            self::Refunded => 'success',
        };
    }

    /** Cuenta para el máximo devolvible (todo menos las rechazadas). */
    public function counts(): bool
    {
        return $this !== self::Rejected;
    }
}
