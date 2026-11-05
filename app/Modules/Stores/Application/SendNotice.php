<?php

namespace App\Modules\Stores\Application;

use App\Modules\Stores\Contracts\Events\ArtistNoticeSent;
use App\Modules\Stores\Contracts\NoticeStatus;
use App\Modules\Stores\Infrastructure\Models\ArtistNotice;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Un artista envía un aviso a Clave de Alma (ADR 0023). Avisa a los administradores
 * por el evento ArtistNoticeSent.
 */
class SendNotice
{
    public const MAX_FILES = 5;

    /**
     * @param  list<string>  $attachments  rutas en el disco privado
     */
    public function __invoke(int $artistId, ?int $userId, string $title, string $message, ?CarbonInterface $eventDate = null, ?int $productId = null, array $attachments = []): ArtistNotice
    {
        if (trim($title) === '' || trim($message) === '') {
            throw new InvalidArgumentException('El aviso necesita un título y un mensaje.');
        }
        if (count($attachments) > self::MAX_FILES) {
            throw new InvalidArgumentException('Como mucho '.self::MAX_FILES.' archivos por aviso.');
        }

        $notice = ArtistNotice::query()->create([
            'artist_id' => $artistId,
            'user_id' => $userId,
            'title' => trim($title),
            'message' => trim($message),
            'event_date' => $eventDate?->toDateString(),
            'product_id' => $productId,
            'attachments' => $attachments,
            'status' => NoticeStatus::New,
        ]);

        ArtistNoticeSent::dispatch($notice->id);

        return $notice;
    }
}
