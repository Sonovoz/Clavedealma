<?php

namespace App\Http\Controllers\Storefront;

use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\StoreAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Imágenes propias de un diseño (resources/designs/<diseño>/). Solo se sirven si alguna
 * tienda con ese diseño es visible para quien las pide: las de una tienda oculta no
 * salen a Internet antes de que el artista la apruebe. Las de la matriz son públicas.
 */
class DesignAssetController
{
    public function __invoke(Request $request, string $design, string $file): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-z0-9-]+$/', $design) && preg_match('/^[A-Za-z0-9._-]+\.(webp|png|jpe?g|svg)$/', $file), 404);

        $visible = $design === 'matriz'
            || Store::query()->where('design', $design)->get()->contains(fn (Store $s): bool => StoreAccess::canView($s, $request->user()));
        $path = resource_path("designs/{$design}/{$file}");
        abort_unless($visible && is_file($path), 404);

        return response()->file($path, [
            'Cache-Control' => StoreAccess::isStaff($request->user()) ? 'private, max-age=600' : 'public, max-age=604800',
        ]);
    }
}
