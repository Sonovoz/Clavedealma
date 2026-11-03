<?php

namespace App\Modules\Stores\Domain;

use InvalidArgumentException;

/**
 * Porcentaje de comisión del artista, guardado en puntos básicos
 * (1 % = 100 pb) para no usar decimales: 25,5 % = 2550.
 */
final readonly class Commission
{
    public const MAX_BASIS_POINTS = 10_000;

    private function __construct(public int $basisPoints)
    {
        if ($basisPoints < 0 || $basisPoints > self::MAX_BASIS_POINTS) {
            throw new InvalidArgumentException('La comisión debe estar entre 0 % y 100 %.');
        }
    }

    public static function fromBasisPoints(int $basisPoints): self
    {
        return new self($basisPoints);
    }

    public static function fromPercent(string|int|float $percent): self
    {
        return new self((int) round(((float) str_replace(',', '.', (string) $percent)) * 100));
    }

    public function percent(): float
    {
        return $this->basisPoints / 100;
    }

    /**
     * Comisión en céntimos sobre una base en céntimos, redondeando al céntimo
     * más cercano (los medios céntimos hacia arriba).
     */
    public function of(int $baseCents): int
    {
        return intdiv($baseCents * $this->basisPoints + 5_000, 10_000);
    }

    public function label(): string
    {
        return rtrim(rtrim(number_format($this->percent(), 2, ',', '.'), '0'), ',').' %';
    }
}
