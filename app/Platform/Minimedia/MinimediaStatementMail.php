<?php

namespace App\Platform\Minimedia;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Resumen mensual de la comisión para Minimedia, con PDF y CSV (ADR 0029). */
class MinimediaStatementMail extends Mailable
{
    public function __construct(public readonly MinimediaStatement $statement, private readonly string $pdf, private readonly string $csv) {}

    public function envelope(): Envelope
    {
        $v = CloseMinimediaMonth::view($this->statement);

        return new Envelope(subject: 'Clave de Alma · Comisión Minimedia de '.mb_strtolower((string) $v['label']).': '.$v['commission']);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.minimedia.statement', with: CloseMinimediaMonth::view($this->statement));
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        $name = 'minimedia-'.$this->statement->month;

        return [
            Attachment::fromData(fn (): string => $this->pdf, $name.'.pdf')->withMime('application/pdf'),
            Attachment::fromData(fn (): string => $this->csv, $name.'.csv')->withMime('text/csv'),
        ];
    }
}
