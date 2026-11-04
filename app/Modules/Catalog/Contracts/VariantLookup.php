<?php

namespace App\Modules\Catalog\Contracts;

interface VariantLookup
{
    /**
     * Variantes por id (las que no existen no aparecen).
     *
     * @param  list<int>  $variantIds
     * @return array<int, SellableVariant>
     */
    public function find(array $variantIds): array;
}
