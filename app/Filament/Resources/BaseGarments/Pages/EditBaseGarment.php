<?php

namespace App\Filament\Resources\BaseGarments\Pages;

use App\Filament\Resources\BaseGarments\BaseGarmentResource;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditBaseGarment extends EditRecord
{
    protected static string $resource = BaseGarmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newArticle')
                ->label('Crear artículo con esta prenda')
                ->icon(Heroicon::OutlinedPlus)
                ->url(fn (): string => ProductResource::getUrl('create', ['prenda' => $this->getRecord()->getKey()])),
            DeleteAction::make(),
        ];
    }
}
