<?php

namespace App\Modules\Payments\Contracts;

final readonly class RefundResult
{
    public function __construct(
        public bool $succeeded,
        public ?string $providerReference = null,
        public ?string $message = null,
    ) {}
}
