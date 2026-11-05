<?php

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Contracts\PricedCart;
use App\Modules\Cart\Contracts\PricedLine;
use App\Modules\Catalog\Contracts\VariantLookup;
use App\Modules\Inventory\Contracts\StockAvailability;
use App\Modules\Stores\Contracts\StoreDirectory;

/**
 * Calcula la cesta con los datos actuales: precio vigente, artículo a la venta,
 * tienda publicada y unidades disponibles. Nunca se fía de nada guardado en la cesta.
 */
class PriceCart
{
    public function __construct(
        private readonly VariantLookup $variants,
        private readonly StockAvailability $stock,
        private readonly StoreDirectory $stores,
    ) {}

    /**
     * @param  array<int, int>  $lines  variante → unidades
     * @param  bool  $staff  el equipo puede hacer compras de prueba en tiendas ocultas
     */
    public function __invoke(array $lines, bool $staff = false): PricedCart
    {
        $ids = array_keys($lines);
        $variants = $this->variants->find($ids);
        // Inventario por producto (ADR 0022): el límite se reparte entre sus tallas y colores.
        $remaining = $this->stock->productLimits(array_values(array_unique(array_map(fn ($v): int => $v->productId, $variants))));
        $stores = $this->stores->find(array_values(array_unique(array_map(fn ($v): int => $v->storeId, $variants))));

        $priced = [];
        foreach ($lines as $id => $requested) {
            $variant = $variants[$id] ?? null;
            $store = $variant === null ? null : ($stores[$variant->storeId] ?? null);
            if ($variant === null || $store === null) {
                continue; // ya no existe: se cae de la cesta sin más
            }

            $max = $remaining[$variant->productId] ?? null;
            $problem = null;
            $quantity = $requested;

            if (! $variant->onSale || (! $store->sells() && ! $staff)) {
                [$quantity, $problem] = [0, 'Este artículo ya no está a la venta.'];
            } elseif ($max !== null && $max < 1) {
                [$quantity, $problem] = [0, 'Agotado.'];
            } elseif ($max !== null && $requested > $max) {
                [$quantity, $problem] = [$max, $max === 1 ? 'Solo queda 1 unidad.' : "Solo quedan {$max} unidades."];
            }

            if ($max !== null) {
                $remaining[$variant->productId] = max(0, $max - $quantity);
            }

            $priced[] = new PricedLine($variant, $store, $quantity, $requested, $max, $problem);
        }

        return new PricedCart($priced, (int) config('clavedealma.shop.default_weight_grams', 400));
    }
}
