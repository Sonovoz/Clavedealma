<?php

namespace App\Platform\Artists\Mail;

use App\Modules\Stores\Infrastructure\Models\ArtistNotice;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Emails de los avisos de artistas (ADR 0023): al equipo cuando llega uno nuevo
 * ($forTeam) y al artista cuando el equipo lo atiende.
 */
class ArtistNoticeMail extends Mailable
{
    public function __construct(public readonly ArtistNotice $notice, public readonly bool $forTeam) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->forTeam
            ? 'Aviso de '.$this->notice->artist->display_name.': '.$this->notice->title
            : 'Tu aviso «'.$this->notice->title.'»: '.mb_strtolower($this->notice->status->label()));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.artists.notice', with: ['notice' => $this->notice, 'forTeam' => $this->forTeam]);
    }
}
