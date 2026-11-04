<?php

namespace App\Modules\Orders\Contracts;

use App\Support\Contracts\HasLabel;

/**
 * Estados del pedido en la fase de compra. La fabricación y el envío (fase 5)
 * añadirán los suyos.
 */
enum OrderStatus: string implements HasLabel
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pendiente de pago',
            self::Paid => 'Pagado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'gray',
        };
    }
}
