<?php

namespace App\Platform\Legal;

use App\Modules\Invoicing\Infrastructure\Models\InvoiceIssuer;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Storefront\Urls;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Genera los textos legales (aviso legal, privacidad, cookies, condiciones de venta y envíos
 * y devoluciones) de Clave de Alma y de cada tienda, solo con datos reales del sistema: datos
 * fiscales, tiendas, tarifas de envío, reglas de devolución y cookies que usa la web. Lo que
 * falta sale como «[pendiente: …]». Son una base: los revisa la asesoría antes de darlos por buenos.
 */
final class LegalGenerator
{
    /**
     * Regenera todo. Solo crea versión nueva donde el texto cambia.
     *
     * @return int versiones nuevas
     */
    public function generateAll(): int
    {
        $created = $this->generateFor(null);
        foreach (Store::query()->with('artist')->orderBy('id')->get() as $store) {
            $created += $this->generateFor($store);
        }

        return $created;
    }

    public function generateFor(?Store $store): int
    {
        $created = 0;
        foreach (LegalPage::cases() as $page) {
            [$content, $missing] = $this->render($page, $store);
            $created += DB::transaction(function () use ($page, $store, $content, $missing): int {
                $current = LegalDocument::query()->where('page', $page)->where('store_id', $store?->id)->lockForUpdate()->latest('version')->first();
                if ($current !== null && $current->content === $content) {
                    return 0;
                }
                LegalDocument::query()->create([
                    'page' => $page,
                    'store_id' => $store?->id,
                    'version' => ($current->version ?? 0) + 1,
                    'content' => $content,
                    'missing' => $missing === [] ? null : $missing,
                    'generated_at' => now(),
                ]);

                return 1;
            });
        }

        return $created;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    public function render(LegalPage $page, ?Store $store): array
    {
        $missing = [];
        $issuer = InvoiceIssuer::current();
        // Dato del emisor o marca visible de que falta (y se apunta en la lista).
        $f = function (?string $value, string $label) use (&$missing): string {
            $value = trim((string) $value);
            if ($value === '') {
                $missing[] = $label;

                return '**[pendiente: '.$label.']**';
            }

            return e($value);
        };

        $returnsAddress = (string) config('clavedealma.returns.address');
        $content = view('legal.'.$page->value, [
            'f' => $f,
            'issuer' => $issuer,
            'site' => rtrim((string) config('app.url'), '/'),
            'store' => $store === null ? null : [
                'name' => $store->name,
                'artist' => $store->artist->display_name,
                'url' => Urls::home($store),
            ],
            'shipping' => $this->shipping(),
            'blocked' => array_map(fn (string $z): string => ShippingZone::from($z)->label(), (array) config('clavedealma.shop.blocked_zones', [])),
            'returnsAddress' => $returnsAddress,
            'sessionCookie' => (string) config('session.cookie'),
            // Pasarela de pago activa (ADR 0026): cambia quién cobra y qué cookies hay.
            'gateway' => config('payments.driver') === 'stripe' ? 'stripe' : 'redsys',
            'securityDays' => (int) config('clavedealma.security.retention_days', 365),
            'pageTitle' => $page->label(),
        ])->render();

        return [trim($content)."\n", array_values(array_unique($missing))];
    }

    /**
     * Destinos a los que se vende hoy, con su precio mínimo, envío gratis y plazo.
     *
     * @return list<array{zone: string, from: string, free: ?string, days: string}>
     */
    private function shipping(): array
    {
        $blocked = (array) config('clavedealma.shop.blocked_zones', []);

        return ZoneRate::query()->with(['carrier', 'bands'])->where('active', true)->get()
            ->filter(fn (ZoneRate $r): bool => $r->carrier !== null && $r->carrier->active && $r->bands->isNotEmpty() && ! in_array($r->zone->value, $blocked, true))
            ->map(fn (ZoneRate $r): array => [
                'zone' => $r->zone->label(),
                'from' => Money::fromCents((int) $r->bands->min('price_cents'))->format(),
                'free' => $r->free_from_cents === null ? null : Money::fromCents($r->free_from_cents)->format(),
                'days' => $r->min_days === $r->max_days ? "{$r->min_days}" : "{$r->min_days}–{$r->max_days}",
            ])->values()->all();
    }
}
