<?php

namespace App\Modules\Orders\Application;

use App\Modules\Orders\Infrastructure\Mail\NewOrderAlert;
use App\Modules\Orders\Infrastructure\Mail\OrderConfirmation;
use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * «Acciones del pedido» de WooCommerce (ADR 0025): enviar otra vez los detalles del
 * pedido al cliente o el aviso de nuevo pedido al equipo. Queda en el historial.
 */
class ResendOrderEmails
{
    public function toCustomer(Order $order, ?string $author = null): void
    {
        if (! $order->status->isPaid()) {
            throw new InvalidArgumentException('El pedido aún no está pagado: no hay confirmación que enviar.');
        }
        Mail::to($order->email, $order->customer_name)->send(new OrderConfirmation($order));
        $order->log('email_sent', 'Detalles del pedido reenviados a '.$order->email, $author);
    }

    public function toTeam(Order $order, ?string $author = null): string
    {
        $team = config('clavedealma.orders.alert_email') ?: config('clavedealma.superadmin.email');
        if (! is_string($team) || $team === '') {
            throw new InvalidArgumentException('No hay ningún email del equipo configurado (ORDERS_ALERT_EMAIL o SUPERADMIN_EMAIL).');
        }
        Mail::to($team)->send(new NewOrderAlert($order));
        $order->log('email_sent', 'Aviso de nuevo pedido reenviado a '.$team, $author);

        return $team;
    }
}
