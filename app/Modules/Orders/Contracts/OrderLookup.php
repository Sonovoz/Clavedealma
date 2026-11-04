<?php

namespace App\Modules\Orders\Contracts;

interface OrderLookup
{
    public function find(int $orderId): ?OrderSummary;

    public function findByToken(string $token): ?OrderSummary;

    /** Anota algo en el historial del pedido (p. ej. una devolución). */
    public function note(int $orderId, string $type, ?string $note = null): void;
}
