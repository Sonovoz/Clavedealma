<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\Pages\Concerns\SavesArticle;
use App\Filament\Resources\Products\ProductResource;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Urls;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditProduct extends EditRecord
{
    use SavesArticle;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Product $product */
        $product = $this->getRecord();
        $store = Store::query()->find($product->store_id);

        return [
            // El equipo ve también los borradores (vista previa), así que siempre se puede abrir.
            Action::make('viewInStore')
                ->label('Ver en la tienda')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible($store !== null)
                ->url($store === null ? null : Urls::product($store, $product->slug), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
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
