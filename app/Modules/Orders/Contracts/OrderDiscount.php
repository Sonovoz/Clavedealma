<?php

namespace App\Modules\Orders\Contracts;

/**
 * Descuento de un cupón ya calculado para un pedido (ADR 0024): el código y cuánto se
 * descuenta de cada línea (por id de variante, céntimos con IVA). El pedido lo guarda
 * en cada línea, así que el IVA, la factura, la comisión y las devoluciones salen de
 * lo que de verdad se cobra.
 */
final readonly class OrderDiscount
{
    /**
     * @param  array<int, int>  $lines
     */
    public function __construct(
        public string $code,
        public array $lines,
    ) {}

    public function forVariant(int $variantId): int
    {
        return max(0, $this->lines[$variantId] ?? 0);
    }
}
