<?php

namespace App\Modules\Returns\Domain;

use DateTimeImmutable;

/**
 * Derecho de desistimiento (14 días naturales desde la entrega) y cálculo del importe.
 * Reglas puras: se prueban aisladas.
 */
final class ReturnPolicy
{
    public const WINDOW_DAYS = 14;

    /**
     * Último día para pedir la devolución. Si no se ha marcado la entrega, se cuenta desde
     * el envío más el plazo máximo de entrega (lo más favorable para el cliente).
     */
    public static function deadline(?DateTimeImmutable $deliveredAt, ?DateTimeImmutable $shippedAt, int $deliveryMaxDays): ?DateTimeImmutable
    {
        $from = $deliveredAt ?? $shippedAt?->modify("+{$deliveryMaxDays} days");

        return $from?->setTime(23, 59, 59)->modify('+'.self::WINDOW_DAYS.' days');
    }

    /**
     * Unidades que aún se pueden devolver de cada línea (las personalizadas, ninguna).
     *
     * @param  list<array{id: int, quantity: int, personalized: bool}>  $lines
     * @param  array<int, int>  $alreadyReturned  línea → unidades en otras devoluciones no rechazadas
     * @return array<int, int>
     */
    public static function returnable(array $lines, array $alreadyReturned): array
    {
        $result = [];
        foreach ($lines as $line) {
            $result[$line['id']] = $line['personalized'] ? 0 : max(0, $line['quantity'] - ($alreadyReturned[$line['id']] ?? 0));
        }

        return $result;
    }

    /**
     * Importe a devolver: las piezas devueltas y, si con esta devolución vuelve el pedido
     * entero, también el envío pagado (art. 107 TRLGDCU). El envío de vuelta lo paga el cliente.
     *
     * @param  list<array{id: int, quantity: int, unit_price_cents: int}>  $lines
     * @param  array<int, int>  $alreadyReturned  línea → unidades en otras devoluciones no rechazadas
     * @param  array<int, int>  $thisReturn  línea → unidades de esta devolución
     */
    public static function refundCents(array $lines, array $alreadyReturned, array $thisReturn, int $shippingCents): int
    {
        $amount = 0;
        $whole = true;
        foreach ($lines as $line) {
            $now = $thisReturn[$line['id']] ?? 0;
            $amount += $now * $line['unit_price_cents'];
            if (($alreadyReturned[$line['id']] ?? 0) + $now < $line['quantity']) {
                $whole = false;
            }
        }

        return $amount + ($whole ? $shippingCents : 0);
    }
}
