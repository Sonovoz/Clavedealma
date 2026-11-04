<?php

namespace App\Modules\Cart\Application;

use App\Modules\Cart\Domain\CartLines;
use Illuminate\Contracts\Session\Session;

/**
 * Cesta única para todas las tiendas (un solo pago), guardada en la sesión del visitante.
 * Solo guarda variante → unidades: el precio se calcula siempre con PriceCart.
 */
class CartStore
{
    private const KEY = 'cart.lines';

    public function __construct(private readonly Session $session) {}

    /**
     * @return array<int, int>
     */
    public function lines(): array
    {
        return CartLines::sanitize($this->session->get(self::KEY), $this->max());
    }

    public function add(int $variantId, int $quantity): void
    {
        $this->save(CartLines::add($this->lines(), $variantId, $quantity, $this->max()));
    }

    public function set(int $variantId, int $quantity): void
    {
        $this->save(CartLines::set($this->lines(), $variantId, $quantity, $this->max()));
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    public function count(): int
    {
        return array_sum($this->lines());
    }

    /**
     * @param  array<int, int>  $lines
     */
    private function save(array $lines): void
    {
        $this->session->put(self::KEY, $lines);
    }

    private function max(): int
    {
        return (int) config('clavedealma.shop.max_per_line', 10);
    }
}
