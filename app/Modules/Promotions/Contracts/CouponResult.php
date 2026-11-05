<?php

namespace App\Modules\Promotions\Contracts;

/**
 * Resultado de aplicar un cupón a un carrito: el descuento de cada línea (céntimos con
 * IVA, por clave de línea) y si deja el envío gratis. Si no vale, $error dice por qué.
 */
final readonly class CouponResult
{
    /**
     * @param  array<int, int>  $discounts
     */
    public function __construct(
        public string $code,
        public ?string $error = null,
        public array $discounts = [],
        public bool $freeShipping = false,
        public string $label = '',
    ) {}

    public function ok(): bool
    {
        return $this->error === null;
    }

    public function discountCents(): int
    {
        return array_sum($this->discounts);
    }

    public static function invalid(string $code, string $error): self
    {
        return new self($code, $error);
    }
}
