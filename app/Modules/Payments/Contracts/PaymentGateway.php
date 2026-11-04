<?php

namespace App\Modules\Payments\Contracts;

/**
 * Pasarela de pago (ADR 0002). Drivers: «stripe» y «fake» (pago simulado, solo fuera
 * de producción). Los datos de la tarjeta nunca pasan por nuestro servidor.
 */
interface PaymentGateway
{
    public function name(): string;

    /** Clave pública para el navegador (null si el driver no la usa). */
    public function publicKey(): ?string;

    public function start(PaymentRequest $request): StartedPayment;

    /**
     * Verifica la firma del aviso y lo traduce. Lanza InvalidWebhook si no es auténtico.
     */
    public function parseWebhook(string $payload, ?string $signature): GatewayEvent;

    /** Estado actual de un cobro (para no depender solo del webhook al volver del pago). */
    public function fetch(string $providerReference): GatewayEvent;

    public function cancel(string $providerReference): void;
}
