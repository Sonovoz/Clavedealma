<?php

namespace App\Modules\Orders\Infrastructure\Mail;

use App\Modules\Orders\Infrastructure\Models\Order;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirmación de pedido para el cliente (se envía al confirmarse el pago).
 */
class OrderConfirmation extends Mailable
{
    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu pedido '.$this->order->reference.' en Clave de Alma');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.orders.confirmation', with: ['order' => $this->order->loadMissing('lines')]);
    }
}
