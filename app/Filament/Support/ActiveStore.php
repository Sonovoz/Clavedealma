<?php

namespace App\Filament\Support;

use App\Modules\Stores\Infrastructure\Models\Store;

/**
 * Tienda en la que se está trabajando en el panel («Tienda: Farruquito ▾»).
 * Productos, colecciones y prendas base se ven y se crean siempre en esta tienda,
 * para que nunca se mezclen artículos de artistas distintos. Se guarda en la sesión.
 */
final class ActiveStore
{
    private const KEY = 'panel.active_store_id';

    public static function get(): ?Store
    {
        $id = session(self::KEY);
        $store = $id === null ? null : Store::query()->find($id);

        if ($store === null) {
            $store = Store::query()->orderBy('name')->first();
            session([self::KEY => $store?->id]);
        }

        return $store;
    }

    public static function id(): ?int
    {
        return self::get()?->id;
    }

    public static function set(int $storeId): void
    {
        if (Store::query()->whereKey($storeId)->exists()) {
            session([self::KEY => $storeId]);
        }
    }
}
