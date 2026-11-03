<?php

namespace App\Filament\Resources\BaseGarments\Pages;

use App\Filament\Resources\BaseGarments\BaseGarmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBaseGarments extends ListRecords
{
    protected static string $resource = BaseGarmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
