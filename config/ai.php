<?php

/*
| Asistente de IA del panel (docs/seo/README.md → «IA»). Va detrás de un contrato propio
| (App\Platform\Ai\TextGenerator), así se puede cambiar de proveedor sin tocar el código.
|
| - AI_DRIVER=none: sin IA (los botones aparecen desactivados). Es el valor por defecto.
| - AI_DRIVER=openai: cualquier API con el formato de OpenAI (/chat/completions):
|   OpenAI, Mistral, Groq, OpenRouter, Together, un servidor propio… Basta con
|   AI_BASE_URL, AI_API_KEY y AI_MODEL (el nombre exacto que indique el proveedor).
|
| La IA solo PROPONE textos: una persona los revisa y guarda. Nunca se le envían datos
| de clientes; solo datos del catálogo.
*/
return [

    'driver' => env('AI_DRIVER', 'none'),

    'openai' => [
        'base_url' => rtrim((string) env('AI_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL'),
        'timeout' => (int) env('AI_TIMEOUT', 60),
        // Algunos proveedores aceptan response_format JSON; si el vuestro no, ponedlo a false.
        'json_mode' => filter_var(env('AI_JSON_MODE', true), FILTER_VALIDATE_BOOL),
        // Nombre del parámetro de longitud máxima: max_tokens (la mayoría) o max_completion_tokens.
        'max_tokens_param' => env('AI_MAX_TOKENS_PARAM', 'max_tokens'),
    ],

];
