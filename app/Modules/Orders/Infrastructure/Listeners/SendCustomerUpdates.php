<?php

namespace App\Modules\Orders\Infrastructure\Listeners;

use App\Modules\Orders\Contracts\Events\OrderShipped;
use App\Modules\Orders\Contracts\Events\PaidOrderCancelled;
use App\Modules\Orders\Infrastructure\Mail\OrderCancelledMail;
use App\Modules\Orders\Infrastructure\Mail\OrderShippedMail;
use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Avisos al cliente cuando su pedido sale o se cancela. Un fallo del correo queda en el
 * historial del pedido y nunca deshace el paso.
 */
class SendCustomerUpdates
{
    public function shipped(OrderShipped $event): void
    {
        $order = Order::query()->find($event->orderId);
        if ($order !== null) {
            $this->send($order, new OrderShippedMail($order));
        }
    }

    public function cancelled(PaidOrderCancelled $event): void
    {
        $order = Order::query()->with('events')->find($event->orderId);
        if ($order === null) {
            return;
        }
        $note = (string) $order->events->where('type', 'cancelled')->last()?->note;
        $this->send($order, new OrderCancelledMail($order, Str::before($note, ' · por ')));
    }

    private function send(Order $order, Mailable $mail): void
    {
        try {
            Mail::to($order->email, $order->customer_name)->send($mail);
            $order->log('email_sent', $order->email);
        } catch (Throwable $e) {
            Log::error('No se pudo avisar del pedido '.$order->reference.': '.$e->getMessage());
            $order->log('email_failed', $e->getMessage());
        }
    }
}
