<?php

namespace App\Filament\Resources\Products\Pages\Concerns;

use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Inventory\Contracts\StockStatus;
use App\Modules\Inventory\Infrastructure\Models\ProductStock;
use App\Platform\Catalog\ArticleEditor;
use Filament\Notifications\Notification;

/**
 * Generador de variantes, inventario y comprobación de publicación al guardar la ficha.
 */
trait SavesArticle
{
    /** @var array<string, mixed> */
    protected array $generator = [];

    /** @var array<string, mixed> */
    protected array $inventory = [];

    /**
     * @return array<string, mixed>
     */
    protected static function emptyGenerator(): array
    {
        return ['colors' => [], 'sizes' => [], 'one_size' => false];
    }

    /**
     * Pestaña «Inventario» con lo guardado ahora. «loaded» es la cantidad al abrir la ficha:
     * al guardar se aplica la diferencia, para no pisar lo vendido mientras tanto.
     *
     * @return array<string, mixed>
     */
    protected static function inventoryState(?Product $product): array
    {
        $stock = $product === null ? null : ProductStock::query()->where('product_id', $product->id)->first();

        return [
            'status' => ($stock->status ?? StockStatus::InStock)->value,
            'managed' => (bool) ($stock->managed ?? false),
            'quantity' => $stock?->managed ? $stock->quantity : null,
            'threshold' => $stock?->low_stock_threshold,
            'loaded' => $stock?->managed ? $stock->quantity : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function takeGenerator(array $data): array
    {
        $this->generator = (array) ($data['generator'] ?? []);
        $this->inventory = (array) ($data['inventory'] ?? []);
        unset($data['generator'], $data['inventory']);

        return $data;
    }

    protected function finishArticle(): void
    {
        $product = $this->getRecord();
        if (! $product instanceof Product) {
            return;
        }

        $result = app(ArticleEditor::class)->afterSave($product, $this->generator, $this->inventory, auth()->id());
        $this->generator = $this->inventory = [];

        if ($result['created'] > 0) {
            Notification::make()->title("{$result['created']} variantes creadas")->success()->send();
        }
        if ($result['problems'] !== []) {
            Notification::make()
                ->title('Guardado como borrador: aún no se puede publicar')
                ->body(implode("\n", $result['problems']))
                ->warning()
                ->persistent()
                ->send();
        }

        // Vaciar el generador para que no se repita al volver a guardar.
        $this->data['generator'] = self::emptyGenerator();
        $this->data['inventory'] = self::inventoryState($product);
        $this->data['status'] = $product->status->value;
    }
}
