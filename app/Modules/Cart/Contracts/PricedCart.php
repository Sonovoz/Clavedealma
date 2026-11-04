<?php

namespace App\Modules\Cart\Contracts;

/**
 * La cesta calculada en el servidor: precios actuales, stock y peso para el envío.
 */
final readonly class PricedCart
{
    /**
     * @param  list<PricedLine>  $lines
     */
    public function __construct(public array $lines, public int $defaultWeightGrams) {}

    /**
     * @return list<PricedLine>
     */
    public function buyableLines(): array
    {
        return array_values(array_filter($this->lines, fn (PricedLine $l): bool => $l->buyable()));
    }

    public function isEmpty(): bool
    {
        return $this->buyableLines() === [];
    }

    public function count(): int
    {
        return array_sum(array_map(fn (PricedLine $l): int => $l->quantity, $this->lines));
    }

    public function subtotalCents(): int
    {
        return array_sum(array_map(fn (PricedLine $l): int => $l->totalCents(), $this->lines));
    }

    public function weightGrams(): int
    {
        return array_sum(array_map(fn (PricedLine $l): int => ($l->variant->weightGrams ?? $this->defaultWeightGrams) * $l->quantity, $this->buyableLines()));
    }

    public function hasProblems(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->problem !== null) {
                return true;
            }
        }

        return false;
    }

    /** Plazo de fabricación del pedido: el del artículo que más tarda. */
    public function leadTimeMaxDays(): int
    {
        return max([0, ...array_map(fn (PricedLine $l): int => $l->variant->leadTimeMaxDays, $this->buyableLines())]);
    }

    public function leadTimeMinDays(): int
    {
        return max([0, ...array_map(fn (PricedLine $l): int => $l->variant->leadTimeMinDays, $this->buyableLines())]);
    }
}
