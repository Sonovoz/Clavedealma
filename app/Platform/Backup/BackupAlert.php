<?php

namespace App\Platform\Backup;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso por email al superadministrador: una copia ha fallado o lleva tiempo sin hacerse.
 */
class BackupAlert extends Notification
{
    public function __construct(public readonly string $subject, public readonly string $body) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Clave de Alma · '.$this->subject)
            ->greeting($this->subject)
            ->line($this->body)
            ->action('Ver las copias de seguridad', url('/admin/copias-de-seguridad'))
            ->line('Desde esa página puedes hacer una copia ahora mismo.');
    }
}
