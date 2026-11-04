<?php

namespace App\Platform\Documents;

use App\Platform\Pdf\PdfRenderer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Respuesta de descarga de un documento PDF (la usan las acciones del panel).
 */
final class Download
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function pdf(string $view, array $data, string $filename, string $orientation = 'portrait'): StreamedResponse
    {
        $content = app(PdfRenderer::class)->render($view, $data + ['generatedAt' => now()->timezone('Europe/Madrid')], $orientation);

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }
}
