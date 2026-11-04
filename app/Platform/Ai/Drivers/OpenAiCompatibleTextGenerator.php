<?php

namespace App\Platform\Ai\Drivers;

use App\Platform\Ai\AiException;
use App\Platform\Ai\TextGenerator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Proveedores con la API de OpenAI (/chat/completions): OpenAI y los muchos
 * proveedores que usan el mismo formato. Configuración en config/ai.php.
 */
class OpenAiCompatibleTextGenerator implements TextGenerator
{
    /**
     * @param  array{base_url: string, api_key: ?string, model: ?string, timeout: int, json_mode: bool, max_tokens_param: string}  $config
     */
    public function __construct(private readonly array $config) {}

    public function available(): bool
    {
        return filled($this->config['api_key']) && filled($this->config['model']);
    }

    public function json(string $system, string $prompt, int $maxTokens = 1500): array
    {
        if (! $this->available()) {
            throw new AiException('La IA no está configurada: faltan AI_API_KEY o AI_MODEL en el .env.');
        }

        $body = [
            'model' => $this->config['model'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
            $this->config['max_tokens_param'] => $maxTokens,
        ];
        if ($this->config['json_mode']) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::withToken((string) $this->config['api_key'])
                ->acceptJson()
                ->timeout($this->config['timeout'])
                ->retry(2, 1500, throw: false)
                ->post($this->config['base_url'].'/chat/completions', $body);
        } catch (ConnectionException) {
            throw new AiException('No se ha podido conectar con el proveedor de IA. Inténtalo de nuevo en un momento.');
        }

        if ($response->failed()) {
            Log::warning('IA: respuesta con error', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 500)]);
            throw new AiException(match ($response->status()) {
                401, 403 => 'El proveedor de IA ha rechazado la clave (AI_API_KEY).',
                404 => 'El proveedor de IA no reconoce el modelo o la dirección (AI_MODEL / AI_BASE_URL).',
                429 => 'El proveedor de IA pide esperar: demasiadas peticiones o saldo agotado.',
                default => 'El proveedor de IA ha devuelto un error ('.$response->status().'). Inténtalo de nuevo.',
            });
        }

        $content = (string) $response->json('choices.0.message.content', '');

        return $this->decode($content);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $content): array
    {
        // Algunos modelos envuelven el JSON en ```json … ```: se toma el primer objeto.
        if (preg_match('/\{.*\}/s', $content, $m) === 1) {
            $data = json_decode($m[0], true);
            if (is_array($data)) {
                return $data;
            }
        }

        throw new AiException('La IA no ha devuelto una respuesta válida. Inténtalo de nuevo.');
    }
}
