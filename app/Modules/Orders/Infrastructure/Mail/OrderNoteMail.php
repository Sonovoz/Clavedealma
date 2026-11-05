<?php

namespace App\Modules\Orders\Infrastructure\Mail;

use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** «Nota para el cliente» de un pedido (ADR 0025). */
class OrderNoteMail extends Mailable
{
    public function __construct(public readonly Order $order, public readonly string $text) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Novedades de tu pedido '.$this->order->reference);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.note', with: ['order' => $this->order, 'text' => $this->text]);
    }
}
