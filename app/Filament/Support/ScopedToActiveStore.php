<?php

namespace App\Filament\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Para los recursos del catálogo: solo se ven y se editan los registros de la tienda activa.
 * Abrir por dirección un registro de otra tienda da «no encontrado».
 */
trait ScopedToActiveStore
{
    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return $query->where($query->getModel()->qualifyColumn('store_id'), ActiveStore::id() ?? 0);
    }

    public static function canCreate(): bool
    {
        return ActiveStore::id() !== null && parent::canCreate();
    }
}
