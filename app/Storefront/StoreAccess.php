<?php

namespace App\Storefront;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Quién ve qué en el escaparate. Una tienda publicada la ve todo el mundo; una oculta
 * o en preparación, solo el equipo de catálogo con sesión iniciada (vista previa).
 */
final class StoreAccess
{
    public static function isStaff(?Authenticatable $user): bool
    {
        return $user instanceof User && $user->managesCatalog();
    }

    public static function canView(Store $store, ?Authenticatable $user): bool
    {
        return $store->status === StoreStatus::Published || self::isStaff($user);
    }

    public static function isPreview(Store $store): bool
    {
        return $store->status !== StoreStatus::Published;
    }
}
