<?php

namespace App\Http\Controllers\Checkout;

use App\Modules\Cart\Application\CartStore;
use App\Modules\Cart\Application\PriceCart;
use App\Storefront\Checkout\CartPresenter;
use App\Storefront\Checkout\Countries;
use App\Storefront\Seo\SeoBuilder;
use App\Storefront\StoreAccess;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Cesta única de Clave de Alma (artículos de cualquier tienda, un solo pago).
 */
class CartController
{
    public function __construct(private readonly CartStore $cart, private readonly PriceCart $price) {}

    public function show(Request $request, SeoBuilder $seo): View
    {
        $priced = ($this->price)($this->cart->lines(), StoreAccess::isStaff($request->user()));

        return view('checkout.cart', [
            'seo' => $seo->checkout('Carrito'),
            'lines' => CartPresenter::lines($priced),
            'subtotal' => Money::fromCents($priced->subtotalCents())->format(),
            'count' => $priced->count(),
            'canCheckout' => ! $priced->isEmpty() && ! $priced->hasProblems(),
            'hasProblems' => $priced->hasProblems(),
            'countries' => Countries::ALL,
        ]);
    }

    public function add(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);
        $variantId = (int) $data['variant_id'];
        $quantity = (int) ($data['quantity'] ?? 1);

        // Se comprueba con el mismo cálculo que la cesta: a la venta, tienda visible y unidades.
        $check = ($this->price)([$variantId => $quantity], StoreAccess::isStaff($request->user()));
        $line = $check->lines[0] ?? null;
        $error = $line === null ? 'Este artículo no existe.' : ($line->quantity < 1 ? $line->problem : null);
        // Como WooCommerce: «“X” se ha añadido a tu carrito.»
        $added = $line === null ? '' : ($quantity > 1 ? "{$quantity} × «{$line->variant->productName}» se han añadido a tu carrito." : "«{$line->variant->productName}» se ha añadido a tu carrito.");

        if ($error === null) {
            $this->cart->add($variantId, $quantity);
        }

        if ($request->expectsJson()) {
            return response()->json(
                ['ok' => $error === null, 'message' => $error ?? $added, 'count' => $this->cart->count(), 'cart_url' => route('cart.show')],
                $error === null ? 200 : 422,
            );
        }

        return back()->with($error === null ? 'cart.added' : 'cart.error', $error ?? $added);
    }

    /**
     * «Actualizar carrito» (todas las cantidades a la vez), quitar una línea (×) o cambiar una sola.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['sometimes', 'array'],
            'quantities.*' => ['integer', 'min:0', 'max:10'],
            'remove' => ['sometimes', 'integer', 'min:1'],
            'variant_id' => ['sometimes', 'integer', 'min:1'],
            'quantity' => ['required_with:variant_id', 'integer', 'min:0', 'max:10'],
        ]);

        if (isset($data['remove'])) {
            $this->cart->set((int) $data['remove'], 0);

            return to_route('cart.show')->with('cart.updated', 'Producto eliminado del carrito.');
        }
        foreach ((array) ($data['quantities'] ?? []) as $variantId => $quantity) {
            $this->cart->set((int) $variantId, (int) $quantity);
        }
        if (isset($data['variant_id'])) {
            $this->cart->set((int) $data['variant_id'], (int) $data['quantity']);
        }

        return to_route('cart.show')->with('cart.updated', 'Carrito actualizado.');
    }
}
