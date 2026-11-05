<?php

namespace App\Modules\Inventory\Infrastructure;

use App\Modules\Catalog\Contracts\VariantLookup;

/**
 * Agrupa las líneas de un pedido o una devolución (que llegan por variante) por producto,
 * que es donde vive el inventario (ADR 0022). Usa el contrato del catálogo.
 */
final class ProductsOfVariants
{
    public function __construct(private readonly VariantLookup $variants) {}

    /**
     * @param  list<array{variant_id: int, quantity: int}>  $lines
     * @return array<int, int> unidades por id de producto
     */
    public function units(array $lines): array
    {
        $found = $this->variants->find(array_map(fn (array $l): int => $l['variant_id'], $lines));

        $units = [];
        foreach ($lines as $line) {
            $productId = ($found[$line['variant_id']] ?? null)?->productId;
            if ($productId !== null) {
                $units[$productId] = ($units[$productId] ?? 0) + $line['quantity'];
            }
        }

        return $units;
    }
}
