<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\Pages\Concerns\SavesArticle;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use SavesArticle;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data + ['generator' => self::emptyGenerator()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->takeGenerator($data);
    }

    protected function afterSave(): void
    {
        $this->finishArticle();
    }
}
