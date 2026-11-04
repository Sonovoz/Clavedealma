<?php

namespace App\Modules\Stores\Contracts;

final readonly class StoreInfo
{
    public function __construct(
        public int $id,
        public string $name,
        /** Clave de la dirección: clavedealma.es/<key>. */
        public string $key,
        public StoreStatus $status,
        public int $commissionBps,
    ) {}

    /** Solo las tiendas publicadas venden al público. */
    public function sells(): bool
    {
        return $this->status === StoreStatus::Published;
    }
}
