<?php

namespace App\Modules\Returns\Infrastructure\Mail;

use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Avisos al cliente sobre su devolución: pedida, aprobada (con instrucciones), rechazada
 * o dinero devuelto.
 */
class ReturnUpdateMail extends Mailable
{
    public const REQUESTED = 'requested';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const REFUNDED = 'refunded';

    public function __construct(public readonly ReturnRequest $return, public readonly string $kind, public readonly string $customerName, public readonly string $orderUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: match ($this->kind) {
            self::REQUESTED => 'Hemos recibido tu solicitud de devolución '.$this->return->reference,
            self::APPROVED => 'Devolución '.$this->return->reference.' aprobada: cómo enviarnos las piezas',
            self::REJECTED => 'Sobre tu solicitud de devolución '.$this->return->reference,
            default => 'Te hemos devuelto el dinero de la devolución '.$this->return->reference,
        });
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.returns.update', with: [
            'return' => $this->return->loadMissing('lines'),
            'kind' => $this->kind,
            'name' => $this->customerName,
            'orderUrl' => $this->orderUrl,
        ]);
    }
}
