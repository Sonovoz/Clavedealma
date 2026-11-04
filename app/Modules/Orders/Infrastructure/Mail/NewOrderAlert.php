<?php

namespace App\Modules\Orders\Infrastructure\Mail;

use App\Modules\Orders\Infrastructure\Models\Order;
use App\Support\Money;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso interno: ha entrado un pedido pagado.
 */
class NewOrderAlert extends Mailable
{
    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: ($this->order->is_test ? '[Prueba] ' : '').'Nuevo pedido '.$this->order->reference.' · '.Money::fromCents($this->order->total_cents)->format());
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.alert', with: ['order' => $this->order->loadMissing('lines')]);
    }
}
