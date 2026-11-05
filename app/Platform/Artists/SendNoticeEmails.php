<?php

namespace App\Platform\Artists;

use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Contracts\Events\ArtistNoticeSent;
use App\Modules\Stores\Contracts\Events\ArtistNoticeUpdated;
use App\Modules\Stores\Infrastructure\Models\ArtistNotice;
use App\Platform\Artists\Mail\ArtistNoticeMail;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Hace llegar los avisos (ADR 0023): uno nuevo, a los administradores por email y en la
 * campana del panel; uno atendido, al artista por email. Un fallo de correo no rompe nada.
 */
class SendNoticeEmails
{
    public function sent(ArtistNoticeSent $event): void
    {
        $notice = ArtistNotice::query()->with('artist')->find($event->noticeId);
        if ($notice === null) {
            return;
        }

        $admins = User::role(Role::Superadmin->value)->get();
        $emails = array_values(array_unique(array_filter([
            config('clavedealma.artists.notice_email'),
            ...$admins->pluck('email')->all(),
        ], fn ($e): bool => is_string($e) && $e !== '')));

        foreach ($emails as $email) {
            $this->mail($email, new ArtistNoticeMail($notice, forTeam: true));
        }

        Notification::make()
            ->title('Aviso de '.$notice->artist->display_name)
            ->body($notice->title)
            ->icon('heroicon-o-megaphone')
            ->actions([Action::make('open')->label('Abrir')->url(url('/admin/avisos/'.$notice->id))->markAsRead()])
            ->sendToDatabase($admins);
    }

    public function updated(ArtistNoticeUpdated $event): void
    {
        $notice = ArtistNotice::query()->with('artist')->find($event->noticeId);
        $userId = $notice?->artist->user_id ?? $notice?->user_id;
        $email = $userId === null ? null : User::query()->whereKey($userId)->value('email');
        if ($notice !== null && is_string($email) && $email !== '') {
            $this->mail($email, new ArtistNoticeMail($notice, forTeam: false));
        }
    }

    private function mail(string $to, ArtistNoticeMail $mail): void
    {
        try {
            Mail::to($to)->send($mail);
        } catch (Throwable $e) {
            Log::error('No se pudo enviar el email del aviso '.$mail->notice->id.': '.$e->getMessage());
        }
    }
}
