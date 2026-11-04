<?php

namespace App\Modules\Returns\Infrastructure\Mail;

use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NewReturnAlert extends Mailable
{
    public function __construct(public readonly ReturnRequest $return) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nueva devolución '.$this->return->reference.' (pedido '.$this->return->order_reference.')');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.returns.alert', with: ['return' => $this->return->loadMissing('lines')]);
    }
}
