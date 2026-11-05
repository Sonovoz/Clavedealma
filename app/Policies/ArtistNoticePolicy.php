<?php

namespace App\Policies;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Infrastructure\Models\ArtistNotice;
use App\Platform\Artists\ArtistScope;

/**
 * Avisos de artistas (ADR 0023): cada artista ve y crea los suyos; el superadministrador
 * los ve todos y los atiende. Nadie los borra.
 */
class ArtistNoticePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || ArtistScope::artist($user) !== null;
    }

    public function view(User $user, ArtistNotice $notice): bool
    {
        return $user->isSuperadmin() || ArtistScope::artist($user)?->id === $notice->artist_id;
    }

    public function create(User $user): bool
    {
        return ! $user->isSuperadmin() && ArtistScope::artist($user) !== null;
    }

    public function update(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function delete(User $user): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    public function replicate(User $user): bool
    {
        return false;
    }
}
