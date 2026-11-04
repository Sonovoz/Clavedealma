<?php

namespace App\Http\Controllers\Checkout;

use App\Modules\Orders\Contracts\OrderLookup;
use App\Modules\Orders\Contracts\OrderSummary;
use App\Modules\Returns\Application\RequestReturn;
use App\Modules\Returns\Application\ReturnOptions;
use App\Modules\Returns\Contracts\ReturnReason;
use App\Storefront\Seo\SeoBuilder;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * El cliente pide una devolución desde la página de su pedido (enlace privado del email).
 */
class ReturnController
{
    public function __construct(private readonly OrderLookup $orders, private readonly ReturnOptions $options) {}

    public function show(string $token, SeoBuilder $seo): View|RedirectResponse
    {
        $order = $this->order($token);
        if (! $this->options->open($order)) {
            return to_route('checkout.thanks', $token)->with('return.error', 'Este pedido ya no admite devoluciones.');
        }
        $returnable = $this->options->returnable($order);

        return view('checkout.return', [
            'seo' => $seo->checkout('Devolver piezas'),
            'order' => $order,
            'deadline' => $this->options->deadline($order)?->format('d/m/Y'),
            'reasons' => collect(ReturnReason::cases())->mapWithKeys(fn (ReturnReason $r): array => [$r->value => $r->label()])->all(),
            'lines' => array_map(fn ($l): array => [
                'id' => $l->id,
                'name' => $l->productName,
                'option' => $l->option,
                'store' => $l->storeName,
                'unit' => Money::fromCents($l->unitPriceCents)->format(),
                'max' => $returnable[$l->id] ?? 0,
                'personalized' => $l->personalized,
                'image' => $l->imagePath === null ? null : Storage::disk('public')->url($l->imagePath),
            ], $order->lines),
        ]);
    }

    public function store(Request $request, string $token, RequestReturn $request_): RedirectResponse
    {
        $order = $this->order($token);
        $data = $request->validate([
            'quantities' => ['required', 'array'],
            'quantities.*' => ['integer', 'min:0', 'max:50'],
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'comment' => ['nullable', 'string', 'max:500'],
        ], ['quantities.required' => 'Elige al menos una pieza para devolver.']);

        try {
            $return = $request_($order, (array) $data['quantities'], ReturnReason::from((string) $data['reason']), $data['comment'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['quantities' => $e->getMessage()]);
        }

        return to_route('checkout.thanks', $token)->with('return.created', $return->reference);
    }

    private function order(string $token): OrderSummary
    {
        return $this->orders->findByToken($token) ?? abort(404);
    }
}
