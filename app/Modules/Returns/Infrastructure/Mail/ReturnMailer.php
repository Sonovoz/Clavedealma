<?php

namespace App\Modules\Returns\Infrastructure\Mail;

use App\Modules\Orders\Contracts\OrderLookup;
use App\Modules\Orders\Contracts\OrderSummary;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envía los avisos de una devolución. Un fallo del correo nunca deshace el paso:
 * queda en el historial del pedido.
 */
class ReturnMailer
{
    public function __construct(private readonly OrderLookup $orders) {}

    public function customer(ReturnRequest $return, OrderSummary $order, string $kind): void
    {
        try {
            Mail::to($order->email, $order->customerName)->send(new ReturnUpdateMail($return, $kind, $order->customerName, route('checkout.thanks', $order->token)));
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el aviso de la devolución '.$return->reference.': '.$e->getMessage());
            $this->orders->note($order->id, 'email_failed', $e->getMessage());
        }
    }

    public function team(ReturnRequest $return, OrderSummary $order): void
    {
        $team = config('clavedealma.orders.alert_email') ?: config('clavedealma.superadmin.email');
        if (! is_string($team) || $team === '') {
            return;
        }
        try {
            Mail::to($team)->send(new NewReturnAlert($return));
        } catch (Throwable $e) {
            Log::error('No se pudo avisar de la devolución '.$return->reference.': '.$e->getMessage());
        }
    }
}
