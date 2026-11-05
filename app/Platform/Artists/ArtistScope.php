<?php

namespace App\Platform\Artists;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Infrastructure\Models\Artist;

/**
 * Qué artista ve cada persona en «Mis ventas» y «Avisos» (ADR 0023): el artista, solo el
 * suyo (Artist.user_id); el superadministrador, el que elija (para verlo como el artista).
 */
final class ArtistScope
{
    public static function artist(?User $user, ?int $chosen = null): ?Artist
    {
        if ($user === null) {
            return null;
        }
        if ($user->isSuperadmin()) {
            return Artist::query()->find($chosen) ?? Artist::query()->orderBy('display_name')->first();
        }

        return $user->isArtist() ? Artist::query()->where('user_id', $user->id)->first() : null;
    }

    /**
     * @return list<int>
     */
    public static function storeIds(?Artist $artist): array
    {
        return $artist === null ? [] : array_values(array_map('intval', $artist->stores()->pluck('id')->all()));
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return Artist::query()->orderBy('display_name')->pluck('display_name', 'id')->all();
    }
}
