<?php

namespace App\Platform\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** «Enviar email de prueba» de Ajustes → Correo. */
class SmtpTestMail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Clave de Alma · Email de prueba');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.smtp-test', with: ['host' => (string) config('mail.mailers.smtp.host'), 'from' => (string) config('mail.from.address')]);
    }
}
