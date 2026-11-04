<?php

namespace App\Modules\Orders\Domain;

use App\Modules\Orders\Contracts\OrderStatus;

/**
 * Pasos permitidos del pedido. Sin saltos: se fabrica, se prepara, se envía y se entrega.
 * Un pedido pagado se puede cancelar (devolviendo el importe) mientras no haya salido.
 */
final class OrderFlow
{
    private const NEXT = [
        'pending_payment' => ['paid', 'cancelled'],
        'paid' => ['in_production', 'cancelled'],
        'in_production' => ['ready_to_ship', 'cancelled'],
        'ready_to_ship' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public static function can(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to->value, self::NEXT[$from->value], true);
    }

    /** Se puede cancelar con devolución del importe: pagado y todavía en nuestras manos. */
    public static function canCancelPaid(OrderStatus $status): bool
    {
        return $status->isPaid() && self::can($status, OrderStatus::Cancelled);
    }
}
