<?php

namespace App\Modules\Orders\Infrastructure\Mail;

use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderCancelledMail extends Mailable
{
    public function __construct(public readonly Order $order, public readonly string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Hemos cancelado tu pedido '.$this->order->reference);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.cancelled', with: ['order' => $this->order, 'reason' => $this->reason]);
    }
}
