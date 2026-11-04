<?php

namespace App\Modules\Orders\Infrastructure\Mail;

use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrderShippedMail extends Mailable
{
    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu pedido '.$this->order->reference.' ya está en camino');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.shipped', with: ['order' => $this->order->loadMissing('lines')]);
    }
}
