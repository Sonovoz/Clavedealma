<?php

namespace App\Http\Controllers\Storefront;

use App\Storefront\Seo\SeoBuilder;
use Illuminate\Contracts\View\View;

/**
 * Textos legales del market. El contenido definitivo lo redacta la asesoría legal antes
 * del lanzamiento: mientras tanto la página lo dice y no se indexa.
 */
class LegalController
{
    private const TITLES = [
        'condiciones-de-venta' => 'Condiciones de venta',
        'privacidad' => 'Política de privacidad',
        'envios-y-devoluciones' => 'Envíos y devoluciones',
    ];

    public function __invoke(string $page, SeoBuilder $seo): View
    {
        $title = self::TITLES[$page];

        return view('checkout.legal', ['title' => $title, 'page' => $page, 'seo' => $seo->checkout($title)]);
    }
}
