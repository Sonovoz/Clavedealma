<?php

namespace App\Filament\Resources\Products\Pages\Concerns;

use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Inventory\Contracts\StockMode;
use App\Platform\Catalog\ArticleEditor;
use Filament\Notifications\Notification;

/**
 * Generador de variantes y comprobación de publicación al guardar la ficha.
 */
trait SavesArticle
{
    /** @var array<string, mixed> */
    protected array $generator = [];

    /**
     * @return array<string, mixed>
     */
    protected static function emptyGenerator(): array
    {
        return ['colors' => [], 'sizes' => [], 'one_size' => false, 'stock_mode' => StockMode::OnDemand->value, 'units' => null, 'threshold' => null];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function takeGenerator(array $data): array
    {
        $this->generator = (array) ($data['generator'] ?? []);
        unset($data['generator']);

        return $data;
    }

    protected function finishArticle(): void
    {
        $product = $this->getRecord();
        if (! $product instanceof Product) {
            return;
        }

        $result = app(ArticleEditor::class)->afterSave($product, $this->generator, auth()->id());
        $this->generator = [];

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
        $this->data['status'] = $product->status->value;
    }
}
