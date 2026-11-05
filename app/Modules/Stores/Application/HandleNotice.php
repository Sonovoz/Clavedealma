<?php

namespace App\Modules\Stores\Application;

use App\Modules\Stores\Contracts\Events\ArtistNoticeUpdated;
use App\Modules\Stores\Contracts\NoticeStatus;
use App\Modules\Stores\Infrastructure\Models\ArtistNotice;

/**
 * El equipo pasa un aviso a «En curso» o «Hecho», con una respuesta opcional para el
 * artista (ADR 0023). El artista recibe un email (ArtistNoticeUpdated).
 */
class HandleNotice
{
    public function __invoke(ArtistNotice $notice, NoticeStatus $status, ?string $reply, int $userId): ArtistNotice
    {
        $reply = $reply === null || trim($reply) === '' ? $notice->reply : trim($reply);

        $notice->update([
            'status' => $status,
            'reply' => $reply,
            'handled_by' => $userId,
            'handled_at' => now(),
        ]);

        ArtistNoticeUpdated::dispatch($notice->id);

        return $notice;
    }
}
