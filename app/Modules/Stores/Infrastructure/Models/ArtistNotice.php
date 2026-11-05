<?php

namespace App\Modules\Stores\Infrastructure\Models;

use App\Modules\Stores\Contracts\NoticeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Aviso de un artista para los administradores (ADR 0023). Los archivos (fotos o PDF)
 * se guardan en el disco privado: solo los ven el artista y el equipo.
 *
 * @property int $id
 * @property int $artist_id
 * @property ?int $user_id
 * @property string $title
 * @property string $message
 * @property ?Carbon $event_date
 * @property ?int $product_id
 * @property ?list<string> $attachments
 * @property NoticeStatus $status
 * @property ?string $reply
 * @property ?int $handled_by
 * @property ?Carbon $handled_at
 * @property Carbon $created_at
 * @property-read Artist $artist
 */
class ArtistNotice extends Model
{
    public const DISK = 'local';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return ['status' => NoticeStatus::class, 'event_date' => 'date', 'attachments' => 'array', 'handled_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
