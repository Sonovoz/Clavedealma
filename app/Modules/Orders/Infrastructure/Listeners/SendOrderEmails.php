<?php

namespace App\Modules\Orders\Infrastructure\Listeners;

use App\Modules\Orders\Contracts\Events\OrderPaid;
use App\Modules\Orders\Infrastructure\Mail\NewOrderAlert;
use App\Modules\Orders\Infrastructure\Mail\OrderConfirmation;
use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Al pagarse: confirmación al cliente y aviso al equipo. Un fallo del correo nunca
 * deshace el pago: queda anotado en el historial del pedido.
 */
class SendOrderEmails
{
    public function handle(OrderPaid $event): void
    {
        $order = Order::query()->find($event->orderId);
        if ($order === null) {
            return;
        }

        try {
            Mail::to($order->email, $order->customer_name)->send(new OrderConfirmation($order));
            $order->log('email_sent', $order->email);
        } catch (Throwable $e) {
            Log::error('No se pudo enviar la confirmación del pedido '.$order->reference.': '.$e->getMessage());
            $order->log('email_failed', $e->getMessage());
        }

        $team = config('clavedealma.orders.alert_email') ?: config('clavedealma.superadmin.email');
        if (is_string($team) && $team !== '') {
            try {
                Mail::to($team)->send(new NewOrderAlert($order));
            } catch (Throwable $e) {
                Log::error('No se pudo enviar el aviso del pedido '.$order->reference.': '.$e->getMessage());
            }
        }
    }
}
