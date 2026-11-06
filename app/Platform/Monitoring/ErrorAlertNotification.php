<?php

namespace App\Platform\Monitoring;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email «Error en la web» al equipo técnico (ErrorAlerts).
 */
class ErrorAlertNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $summary
     */
    public function __construct(public readonly array $summary) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->summary;
        $mail = (new MailMessage)
            ->subject('Clave de Alma · '.$s['title'].': '.class_basename((string) $s['type']))
            ->greeting((string) $s['title'])
            ->line('**'.class_basename((string) $s['type']).'**: '.$s['message'])
            ->line('Dónde: `'.$s['where'].'`')
            ->line('Qué se estaba haciendo: '.$s['context'])
            ->line('Cuándo: '.$s['when'].' · '.$s['environment']);

        if ($s['trace'] !== []) {
            $mail->line('Camino en el código:')->line(implode("  \n", array_map(fn (string $t): string => '`'.$t.'`', (array) $s['trace'])));
        }

        return $mail
            ->line('Este mismo error no se vuelve a avisar en una hora. El detalle completo está en el log del servidor (storage/logs).')
            ->action('Estado del sistema', url('/admin/sistema'));
    }
}
