<?php

namespace App\Modules\Payments\Contracts;

use App\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Succeeded => 'Cobrado',
            self::Failed => 'Rechazado',
            self::Cancelled => 'Anulado',
        };
    }
}
