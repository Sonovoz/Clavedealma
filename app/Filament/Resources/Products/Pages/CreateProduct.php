<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\Pages\Concerns\SavesArticle;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\ActiveStore;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use SavesArticle;

    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['store_id'] = ActiveStore::id(); // siempre la tienda activa

        return $this->takeGenerator($data);
    }

    protected function afterCreate(): void
    {
        $this->finishArticle();
    }

    /** Tras crear, a la ficha: ahí se ven las variantes generadas. */
    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
