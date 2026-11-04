<?php

namespace App\Http\Controllers\Storefront;

use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Legal\LegalDocument;
use App\Platform\Legal\LegalPage;
use App\Storefront\Seo\SeoBuilder;
use App\Storefront\StoreAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Textos legales públicos: los de Clave de Alma (/legal/…) y los de cada tienda
 * (/<tienda>/legal/…). Se generan al guardar los datos fiscales (ADR 0020).
 */
class LegalController
{
    public function market(string $page, SeoBuilder $seo): View
    {
        return $this->show(LegalPage::from($page), null, $seo);
    }

    public function store(Request $request, string $store, string $page, SeoBuilder $seo): View
    {
        $shop = Store::query()->where('subdomain', strtolower($store))->first();
        abort_if($shop === null || ! StoreAccess::canView($shop, $request->user()), 404);

        return $this->show(LegalPage::from($page), $shop, $seo);
    }

    private function show(LegalPage $page, ?Store $store, SeoBuilder $seo): View
    {
        $doc = ($store === null ? null : LegalDocument::current($page, $store->id)) ?? LegalDocument::current($page, null);

        return view('checkout.legal', [
            'title' => $page->label(),
            'doc' => $doc,
            'html' => $doc === null ? null : Str::markdown($doc->content, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'store' => $store,
            'seo' => $seo->checkout($page->label().($store ? ' · '.$store->name : '')),
        ]);
    }
}
