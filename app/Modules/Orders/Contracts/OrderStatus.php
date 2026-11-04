<?php

namespace App\Modules\Orders\Contracts;

use App\Support\Contracts\HasLabel;

/**
 * Estados del pedido. Solo se avanza por los pasos de OrderFlow (Domain).
 */
enum OrderStatus: string implements HasLabel
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case InProduction = 'in_production';
    case ReadyToShip = 'ready_to_ship';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pendiente de pago',
            self::Paid => 'Pagado · por fabricar',
            self::InProduction => 'En fabricación',
            self::ReadyToShip => 'Listo para enviar',
            self::Shipped => 'Enviado',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
        };
    }

    /** Cómo lo lee el cliente en la página de su pedido. */
    public function customerLabel(): string
    {
        return match ($this) {
            self::PendingPayment => 'Esperando el pago',
            self::Paid => 'Pago recibido',
            self::InProduction => 'Fabricando tus piezas',
            self::ReadyToShip => 'Preparando el envío',
            self::Shipped => 'En camino',
            self::Delivered => 'Entregado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Paid => 'info',
            self::InProduction => 'primary',
            self::ReadyToShip => 'primary',
            self::Shipped => 'success',
            self::Delivered => 'success',
            self::Cancelled => 'gray',
        };
    }

    /** El cobro está hecho (y no se ha cancelado). */
    public function isPaid(): bool
    {
        return in_array($this, [self::Paid, self::InProduction, self::ReadyToShip, self::Shipped, self::Delivered], true);
    }

    /**
     * @return list<self>
     */
    public static function paid(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s): bool => $s->isPaid()));
    }
}
