<?php

namespace App\Filament\Resources\BaseGarments\Pages;

use App\Filament\Resources\BaseGarments\BaseGarmentResource;
use App\Filament\Support\ActiveStore;
use Filament\Resources\Pages\CreateRecord;

class CreateBaseGarment extends CreateRecord
{
    protected static string $resource = BaseGarmentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['store_id'] = ActiveStore::id(); // siempre la tienda activa

        return $data;
    }
}
