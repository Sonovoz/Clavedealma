<?php

namespace App\Platform\Pdf;

/**
 * Convierte una vista Blade en un PDF (contrato propio con driver, como el resto de
 * servicios). Funciona igual en Hostinger y en Docker: no necesita navegador ni servicios.
 */
interface PdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     * @return string contenido binario del PDF
     */
    public function render(string $view, array $data, string $orientation = 'portrait'): string;
}
