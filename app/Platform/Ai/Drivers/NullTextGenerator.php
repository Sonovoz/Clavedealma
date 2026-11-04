<?php

namespace App\Platform\Ai\Drivers;

use App\Platform\Ai\AiException;
use App\Platform\Ai\TextGenerator;

/** Sin proveedor de IA (AI_DRIVER=none). */
class NullTextGenerator implements TextGenerator
{
    public function available(): bool
    {
        return false;
    }

    public function json(string $system, string $prompt, int $maxTokens = 1500): array
    {
        throw new AiException('La IA no está configurada: falta AI_DRIVER, AI_API_KEY y AI_MODEL en el .env.');
    }
}
