<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Importe en céntimos enteros. Núcleo compartido por todos los módulos:
 * el dinero nunca se representa con decimales flotantes.
 */
final readonly class Money
{
    private function __construct(public int $cents, public string $currency = 'EUR') {}

    public static function fromCents(int $cents, string $currency = 'EUR'): self
    {
        return new self($cents, $currency);
    }

    /**
     * Acepta "29,90", "29.90", "1.234,50" o "29".
     */
    public static function parse(string $amount, string $currency = 'EUR'): self
    {
        $normalized = str_replace([' ', '€'], '', trim($amount));

        if (str_contains($normalized, ',')) {
            $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        }

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException("Importe no válido: [{$amount}].");
        }

        [$units, $decimals] = array_pad(explode('.', ltrim($normalized, '-')), 2, '0');
        $cents = (int) $units * 100 + (int) str_pad($decimals, 2, '0');

        return new self(str_starts_with($normalized, '-') ? -$cents : $cents, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function multiply(int $quantity): self
    {
        return new self($this->cents * $quantity, $this->currency);
    }

    /**
     * Base imponible de un precio con IVA incluido (tipo en puntos básicos: 21 % = 2100).
     */
    public function withoutVat(int $vatRateBps): self
    {
        return new self(intdiv($this->cents * 10_000 * 2 + (10_000 + $vatRateBps), (10_000 + $vatRateBps) * 2), $this->currency);
    }

    public function format(): string
    {
        $sign = $this->cents < 0 ? '-' : '';
        $abs = abs($this->cents);

        return $sign.number_format(intdiv($abs, 100), 0, ',', '.').','.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT).' €';
    }

    public function decimal(): string
    {
        $abs = abs($this->cents);

        return ($this->cents < 0 ? '-' : '').intdiv($abs, 100).','.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    private function assertSameCurrency(self $other): void
    {
        if ($other->currency !== $this->currency) {
            throw new InvalidArgumentException('No se pueden operar importes de monedas distintas.');
        }
    }
}
