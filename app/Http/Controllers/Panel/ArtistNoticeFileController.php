<?php

namespace App\Http\Controllers\Panel;

use App\Modules\Stores\Infrastructure\Models\ArtistNotice;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Archivos de un aviso de artista (disco privado). Solo los ven el artista del aviso y
 * el superadministrador (ArtistNoticePolicy::view).
 */
class ArtistNoticeFileController
{
    public function __invoke(ArtistNotice $notice, int $index): StreamedResponse
    {
        Gate::authorize('view', $notice);
        $path = ($notice->attachments ?? [])[$index] ?? null;
        abort_if($path === null || ! Storage::disk(ArtistNotice::DISK)->exists($path), 404);

        return Storage::disk(ArtistNotice::DISK)->response($path, basename($path));
    }
}
