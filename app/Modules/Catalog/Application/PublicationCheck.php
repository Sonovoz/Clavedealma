<?php

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Infrastructure\Models\BaseGarment;
use App\Modules\Catalog\Infrastructure\Models\Product;

/**
 * Lo mínimo para publicar un artículo. La usan la ficha del panel y el importador,
 * así por ningún camino se publica algo incompleto.
 */
class PublicationCheck
{
    /**
     * @return list<string> problemas; vacía = se puede publicar
     */
    public function problems(Product $product): array
    {
        $problems = [];

        if ($product->price_cents <= 0) {
            $problems[] = 'El precio debe ser mayor que cero.';
        }
        if (! $product->variants()->where('active', true)->exists()) {
            $problems[] = 'Necesita al menos una talla o color activo.';
        }
        if (! $product->images()->exists()) {
            $problems[] = 'Necesita al menos una foto.';
        }

        $garment = $product->base_garment_id === null ? null : BaseGarment::query()->find($product->base_garment_id);
        if ($garment === null) {
            $problems[] = 'Falta la prenda base: de ella salen la composición y la guía de tallas.';
        } elseif (blank($garment->composition)) {
            $problems[] = "La prenda base «{$garment->name}» no tiene composición (obligatoria para vender textil).";
        }

        return $problems;
    }
}
