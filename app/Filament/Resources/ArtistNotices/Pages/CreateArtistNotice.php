<?php

namespace App\Filament\Resources\ArtistNotices\Pages;

use App\Filament\Resources\ArtistNotices\ArtistNoticeResource;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Application\SendNotice;
use App\Platform\Artists\ArtistScope;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/** Nuevo aviso: lo crea SendNotice, que avisa a los administradores. */
class CreateArtistNotice extends CreateRecord
{
    protected static string $resource = ArtistNoticeResource::class;

    protected static ?string $title = 'Nuevo aviso';

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $artist = ArtistScope::artist($user instanceof User ? $user : null);
        abort_if($artist === null || ! $user instanceof User, 403);

        return app(SendNotice::class)(
            $artist->id,
            $user->id,
            (string) $data['title'],
            (string) $data['message'],
            blank($data['event_date'] ?? null) ? null : CarbonImmutable::parse((string) $data['event_date']),
            blank($data['product_id'] ?? null) ? null : (int) $data['product_id'],
            array_values(array_map('strval', (array) ($data['attachments'] ?? []))),
        );
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Aviso enviado. Te contestamos aquí y por email.';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
