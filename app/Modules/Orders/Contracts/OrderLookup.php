<?php

namespace App\Modules\Orders\Contracts;

interface OrderLookup
{
    public function find(int $orderId): ?OrderSummary;

    public function findByToken(string $token): ?OrderSummary;

    /**
     * Pedidos cobrados de verdad (no de prueba), del más antiguo al más reciente.
     *
     * @return list<int>
     */
    public function paidOrderIds(): array;

    /** Anota algo en el historial del pedido (p. ej. una devolución). */
    public function note(int $orderId, string $type, ?string $note = null): void;
}
