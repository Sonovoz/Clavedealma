<?php

namespace App\Filament\Resources\BaseGarments\Pages;

use App\Filament\Resources\BaseGarments\BaseGarmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBaseGarment extends EditRecord
{
    protected static string $resource = BaseGarmentResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
