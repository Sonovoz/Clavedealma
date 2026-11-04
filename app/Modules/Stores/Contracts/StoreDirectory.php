<?php

namespace App\Modules\Stores\Contracts;

interface StoreDirectory
{
    /**
     * @param  list<int>  $storeIds
     * @return array<int, StoreInfo>
     */
    public function find(array $storeIds): array;
}
