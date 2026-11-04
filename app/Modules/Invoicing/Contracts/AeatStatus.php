<?php

namespace App\Modules\Invoicing\Contracts;

use App\Support\Contracts\HasLabel;

enum AeatStatus: string implements HasLabel
{
    case Pending = 'pending';
    case NotConfigured = 'not_configured';
    case Accepted = 'accepted';
    case AcceptedWithErrors = 'accepted_errors';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de envío',
            self::NotConfigured => 'Sin enviar (envío a la AEAT sin configurar)',
            self::Accepted => 'Aceptada por la AEAT',
            self::AcceptedWithErrors => 'Aceptada con errores',
            self::Rejected => 'Rechazada por la AEAT',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Accepted => 'success',
            self::AcceptedWithErrors, self::Pending => 'warning',
            self::NotConfigured => 'gray',
            self::Rejected => 'danger',
        };
    }
}
