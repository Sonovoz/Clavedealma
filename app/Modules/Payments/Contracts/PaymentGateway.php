<?php

namespace App\Modules\Payments\Contracts;

/**
 * Pasarela de pago (ADR 0002 y 0026). Drivers: «redsys» (la de Clave de Alma), «stripe» y
 * «fake» (pago simulado, solo fuera de producción). Los datos de la tarjeta nunca pasan por
 * nuestro servidor.
 */
interface PaymentGateway
{
    public function name(): string;

    /** Clave pública para el navegador (null si el driver no la usa). */
    public function publicKey(): ?string;

    /**
     * «Métodos de pago» que elige el cliente al finalizar la compra, como en WooCommerce
     * (clave => nombre). Vacío si la pasarela los enseña en su propio formulario (Stripe).
     *
     * @return array<string, string>
     */
    public function methods(): array;

    /** Cobros de prueba (entorno de pruebas de la pasarela): los pedidos se marcan de prueba. */
    public function testMode(): bool;

    public function start(PaymentRequest $request): StartedPayment;

    /**
     * Verifica la firma del aviso y lo traduce. Lanza InvalidWebhook si no es auténtico.
     */
    public function parseWebhook(string $payload, ?string $signature): GatewayEvent;

    /**
     * Resultado firmado que trae el cliente al volver de la página de la pasarela (Redsys).
     * Null si no trae ninguno. Lanza InvalidWebhook si la firma no es válida.
     *
     * @param  array<string, mixed>  $query
     */
    public function returnEvent(array $query): ?GatewayEvent;

    /** Estado actual de un cobro (para no depender solo del webhook al volver del pago). */
    public function fetch(string $providerReference): GatewayEvent;

    public function cancel(string $providerReference): void;

    /** Devuelve (todo o parte de) un cobro hecho. */
    public function refund(string $providerReference, int $amountCents, string $idempotencyKey): RefundResult;
}
