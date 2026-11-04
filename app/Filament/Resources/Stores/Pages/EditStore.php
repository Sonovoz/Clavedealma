<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Stores\StoreResource;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Urls;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditStore extends EditRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Store $store */
        $store = $this->getRecord();

        return [
            Action::make('viewStore')
                ->label('Ver tienda')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(Urls::home($store), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
