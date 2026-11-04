<?php

namespace App\Modules\Orders\Contracts;

use DateTimeImmutable;

/**
 * Lo que otros módulos (devoluciones) necesitan saber de un pedido.
 */
final readonly class OrderSummary
{
    /**
     * @param  list<OrderLineSummary>  $lines
     */
    public function __construct(
        public int $id,
        public string $reference,
        public string $token,
        public OrderStatus $status,
        public string $email,
        public string $customerName,
        public int $shippingCents,
        public int $totalCents,
        public int $deliveryMaxDays,
        public ?DateTimeImmutable $shippedAt,
        public ?DateTimeImmutable $deliveredAt,
        public bool $isTest,
        public array $lines,
    ) {}

    public function line(int $id): ?OrderLineSummary
    {
        foreach ($this->lines as $line) {
            if ($line->id === $id) {
                return $line;
            }
        }

        return null;
    }
}
