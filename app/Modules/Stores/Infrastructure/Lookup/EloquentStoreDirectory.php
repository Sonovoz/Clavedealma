<?php

namespace App\Modules\Stores\Infrastructure\Lookup;

use App\Modules\Stores\Contracts\StoreDirectory;
use App\Modules\Stores\Contracts\StoreInfo;
use App\Modules\Stores\Infrastructure\Models\Store;

class EloquentStoreDirectory implements StoreDirectory
{
    public function find(array $storeIds): array
    {
        if ($storeIds === []) {
            return [];
        }

        return Store::query()->whereKey(array_values(array_unique($storeIds)))->get()
            ->mapWithKeys(fn (Store $s): array => [$s->id => new StoreInfo($s->id, $s->name, $s->subdomain, $s->status, $s->commission_bps)])
            ->all();
    }
}
