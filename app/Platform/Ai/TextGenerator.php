<?php

namespace App\Platform\Ai;

/**
 * Contrato del proveedor de IA. Cada proveedor es un driver (config/ai.php).
 */
interface TextGenerator
{
    /** ¿Hay un proveedor configurado? Si no, el panel desactiva los botones de IA. */
    public function available(): bool;

    /**
     * Pide una respuesta en JSON y la devuelve decodificada.
     *
     * @return array<string, mixed>
     *
     * @throws AiException si no hay proveedor, falla la llamada o la respuesta no es JSON
     */
    public function json(string $system, string $prompt, int $maxTokens = 1500): array;
}
