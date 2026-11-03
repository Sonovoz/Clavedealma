<?php

namespace App\Support\Contracts;

/**
 * Enum o valor con un nombre legible en español para la interfaz.
 */
interface HasLabel
{
    public function label(): string;
}
