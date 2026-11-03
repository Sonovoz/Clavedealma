<?php

namespace App\Modules\Catalog\Domain;

use InvalidArgumentException;

/**
 * Plazo de fabricación bajo pedido, en días hábiles.
 */
final readonly class LeadTime
{
    public function __construct(public int $minDays, public int $maxDays)
    {
        if ($minDays < 0 || $maxDays < $minDays || $maxDays > 60) {
            throw new InvalidArgumentException('Plazo de fabricación no válido.');
        }
    }

    public function label(): string
    {
        if ($this->minDays === $this->maxDays) {
            return "Fabricado en {$this->minDays} días";
        }

        return "Fabricado en {$this->minDays}–{$this->maxDays} días";
    }
}
