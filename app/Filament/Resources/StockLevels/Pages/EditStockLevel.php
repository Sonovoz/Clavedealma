<?php

namespace App\Filament\Resources\StockLevels\Pages;

use App\Filament\Resources\StockLevels\StockLevelResource;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use Filament\Resources\Pages\EditRecord;

class EditStockLevel extends EditRecord
{
    protected static string $resource = StockLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [StockLevelResource::adjustAction()->record($this->getRecord())];
    }

    public function getTitle(): string
    {
        $sku = ProductVariant::query()->whereKey($this->getRecord()->getAttribute('product_variant_id'))->value('sku');

        return "Stock · {$sku} · {$this->getRecord()->getAttribute('quantity')} unidades";
    }
}
