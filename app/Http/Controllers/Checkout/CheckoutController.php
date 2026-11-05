<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Requests\CheckoutRequest;
use App\Modules\Cart\Application\CartStore;
use App\Modules\Cart\Application\PriceCart;
use App\Modules\Cart\Contracts\PricedCart;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Application\CancelPendingOrder;
use App\Modules\Orders\Application\PlaceOrder;
use App\Modules\Orders\Contracts\CheckoutDetails;
use App\Modules\Orders\Contracts\OrderLookup;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Application\HandleGatewayEvent;
use App\Modules\Payments\Application\StartPayment;
use App\Modules\Payments\Application\SyncOrderPayments;
use App\Modules\Payments\Contracts\GatewayEvent;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Contracts\PaymentUnavailable;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Returns\Application\ReturnOptions;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Modules\Shipping\Contracts\ShippingQuote;
use App\Modules\Shipping\Contracts\ShippingQuoter;
use App\Platform\Documents\Download;
use App\Platform\Documents\InvoiceDocument;
use App\Storefront\Checkout\CartPresenter;
use App\Storefront\Checkout\Countries;
use App\Storefront\Seo\SeoBuilder;
use App\Storefront\StoreAccess;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «Finalizar compra» como WooCommerce: datos, «Tu pedido» y pago en una sola página,
 * y después «Pedido recibido». Todo importe se calcula en el servidor; el navegador solo
 * elige variantes y escribe la dirección. Con Stripe, la página crea el pedido y confirma
 * el pago sin salir de ella; sin JavaScript, el pedido pasa a «Pagar el pedido».
 */
class CheckoutController
{
    public function __construct(
        private readonly CartStore $cart,
        private readonly PriceCart $price,
        private readonly ShippingQuoter $shipping,
        private readonly SeoBuilder $seo,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $priced = $this->priced($request);
        if ($priced->isEmpty() || $priced->hasProblems()) {
            return to_route('cart.show');
        }

        $gateway = app(PaymentGateway::class);

        return view('checkout.details', [
            'seo' => $this->seo->checkout('Finalizar compra'),
            'lines' => CartPresenter::lines($priced),
            'subtotal' => Money::fromCents($priced->subtotalCents())->format(),
            'subtotalCents' => $priced->subtotalCents(),
            'countries' => Countries::ALL,
            'gateway' => $gateway->name(),
            'publicKey' => $gateway->publicKey(),
        ]);
    }

    /** Precio del envío para la dirección que se está escribiendo (lo pide la página). */
    public function quote(Request $request): JsonResponse
    {
        $priced = $this->priced($request);
        [$quote, $error] = $this->quoteFor($priced, (string) $request->query('country', 'ES'), (string) $request->query('postal_code', ''));

        return response()->json($quote === null
            ? ['ok' => false, 'message' => $error]
            : [
                'ok' => true,
                'shipping' => $quote->free ? 'Gratis' : Money::fromCents($quote->priceCents)->format(),
                'total' => Money::fromCents($priced->subtotalCents() + $quote->priceCents)->format(),
                'total_cents' => $priced->subtotalCents() + $quote->priceCents,
                'delivery' => $quote->deliveryLabel(),
                'zone' => $quote->zone->label(),
            ]);
    }

    /**
     * «Realizar el pedido». Con Stripe (petición JSON) devuelve el cobro para confirmarlo en
     * la misma página; con el pago simulado lo resuelve aquí; sin JavaScript, lleva a pagar.
     */
    public function place(CheckoutRequest $request, PlaceOrder $place, StartPayment $start, SyncOrderPayments $sync, CancelPendingOrder $cancel): JsonResponse|RedirectResponse
    {
        $priced = $this->priced($request);
        if ($priced->isEmpty() || $priced->hasProblems()) {
            return $this->leave($request, route('cart.show'), 'El carrito ha cambiado: revísalo antes de pagar.');
        }

        $details = $request->details();
        [$quote, $error] = $this->quoteFor($priced, $details->country, $details->postalCode);
        if ($quote === null) {
            return $request->expectsJson()
                ? response()->json(['message' => $error, 'errors' => ['postal_code' => [$error]]], 422)
                : back()->withInput()->withErrors(['postal_code' => $error]);
        }

        // Como WooCommerce: si el cliente vuelve a pulsar «Realizar el pedido» con el mismo
        // carrito y los mismos datos, se reutiliza el pedido que espera el pago. Si ha cambiado
        // algo, el anterior se anula (y su cobro) para que no queden dos pedidos pagables.
        $fingerprint = $this->fingerprint($priced, $details, $quote);
        $pending = (array) $request->session()->get('checkout.pending', []);
        $order = null;
        $previous = isset($pending['token']) ? Order::query()->with('lines')->where('token', $pending['token'])->first() : null;
        if ($previous !== null && $previous->status === OrderStatus::PendingPayment) {
            // Por si el pago se completó y aún no ha llegado el aviso de la pasarela.
            $sync($previous->id);
            $previous->refresh();
            if ($previous->status->isPaid()) {
                $request->session()->forget('checkout.pending');

                return $this->leave($request, route('checkout.thanks', $previous->token));
            }
            if ($previous->status === OrderStatus::PendingPayment) {
                if (($pending['fingerprint'] ?? null) === $fingerprint) {
                    $order = $previous;
                } else {
                    $cancel($previous->id, 'Sustituido por un pedido nuevo: el cliente cambió los datos o el carrito antes de pagar.');
                }
            }
        }

        if ($order === null) {
            try {
                // Se recarga para tener los valores por defecto de la base de datos (moneda…).
                $order = $this->order($place($priced, $details, $quote, $this->isTest($request, $priced))->token);
            } catch (InvalidArgumentException $e) {
                return $this->leave($request, route('cart.show'), $e->getMessage());
            }
            $request->session()->push('checkout.orders', $order->token);
            $request->session()->put('checkout.pending', ['token' => $order->token, 'fingerprint' => $fingerprint]);
        }

        $gateway = app(PaymentGateway::class);
        if ($gateway->name() === 'fake' && $request->filled('simulate')) {
            return $this->settleFake($order, $request->input('simulate') === 'ok', $start)
                ? to_route('checkout.thanks', $order->token)
                : to_route('checkout.show')->withInput()->with('payment.error', 'El pago se ha rechazado. Puedes intentarlo de nuevo.');
        }

        if (! $request->expectsJson()) {
            return to_route('checkout.pay', $order->token);
        }

        try {
            $payment = $start($order->id, (string) $order->reference, $order->total_cents, $order->currency, $order->email);
        } catch (PaymentUnavailable $e) {
            report($e);

            return response()->json(['message' => 'Ahora mismo no podemos conectar con el sistema de pago. Tu pedido está guardado: vuelve a intentarlo en unos minutos.', 'pay_url' => route('checkout.pay', $order->token)], 503);
        }

        return response()->json([
            'ok' => true,
            'reference' => $order->reference,
            'amount_cents' => $order->total_cents,
            'client_secret' => $payment->clientSecret,
            'return_url' => route('checkout.thanks', $order->token),
            'pay_url' => route('checkout.pay', $order->token),
        ]);
    }

    public function pay(string $token, StartPayment $start, PaymentGateway $gateway): View|RedirectResponse
    {
        $order = $this->order($token);
        if ($order->status !== OrderStatus::PendingPayment) {
            return to_route('checkout.thanks', $order->token);
        }

        try {
            $payment = $start($order->id, (string) $order->reference, $order->total_cents, $order->currency, $order->email);
        } catch (PaymentUnavailable $e) {
            report($e);
            $payment = null;
        }

        return view('checkout.pay', $this->summary($order) + [
            'seo' => $this->seo->checkout('Pagar el pedido'),
            'gateway' => $gateway->name(),
            'publicKey' => $gateway->publicKey(),
            'clientSecret' => $payment?->clientSecret,
            'unavailable' => $payment === null,
            'returnUrl' => route('checkout.thanks', $order->token),
        ]);
    }

    /** Pago simulado (PAYMENT_DRIVER=fake, nunca en producción): el mismo camino que un webhook. */
    public function simulate(Request $request, string $token, StartPayment $start, PaymentGateway $gateway): RedirectResponse
    {
        abort_unless($gateway->name() === 'fake' && ! app()->isProduction(), 404);
        $order = $this->order($token);
        abort_unless($order->status === OrderStatus::PendingPayment, 409);

        $ok = $this->settleFake($order, $request->input('outcome') === 'ok', $start);

        return $ok ? to_route('checkout.thanks', $order->token) : to_route('checkout.pay', $order->token)->with('payment.error', 'El pago se ha rechazado. Puedes intentarlo de nuevo.');
    }

    public function thanks(Request $request, string $token, SyncOrderPayments $sync): View
    {
        $order = $this->order($token);
        if ($order->status === OrderStatus::PendingPayment) {
            $sync($order->id);
            $order->refresh();
        }

        if ($order->status->isPaid() && in_array($order->token, (array) $request->session()->get('checkout.orders', []), true)) {
            $this->cart->clear();
            if (($request->session()->get('checkout.pending')['token'] ?? null) === $order->token) {
                $request->session()->forget('checkout.pending');
            }
        }

        $summary = app(OrderLookup::class)->find($order->id);
        $options = app(ReturnOptions::class);

        $provider = Payment::query()->where('order_id', $order->id)->where('status', PaymentStatus::Succeeded)->value('provider');

        return view('checkout.thanks', $this->summary($order) + [
            'seo' => $this->seo->checkout($order->status === OrderStatus::Paid ? 'Pedido recibido' : 'Pedido '.$order->reference),
            'paymentMethod' => match ($provider) {
                'fake' => 'Pago simulado (prueba)',
                null => '—',
                default => 'Tarjeta u otro método (Stripe)',
            },
            'canReturn' => $summary !== null && $options->open($summary),
            'returnDeadline' => $summary === null ? null : $options->deadline($summary)?->format('d/m/Y'),
            'invoices' => Invoice::query()->where('order_id', $order->id)->orderBy('id')->get()
                ->map(fn (Invoice $i): array => ['code' => $i->code, 'label' => $i->type->label(), 'total' => Money::fromCents($i->total_cents)->format(), 'url' => route('checkout.invoice', [$order->token, $i->code])])->all(),
            'returns' => ReturnRequest::query()->where('order_id', $order->id)->latest('id')->get()
                ->map(fn (ReturnRequest $r): array => ['reference' => $r->reference, 'status' => $r->status->customerLabel(), 'amount' => Money::fromCents($r->refund_cents)->format()])->all(),
        ]);
    }

    /** Factura en PDF, desde el enlace privado del pedido. */
    public function invoice(string $token, string $code): StreamedResponse
    {
        $order = $this->order($token);
        $invoice = Invoice::query()->where('order_id', $order->id)->where('code', $code)->firstOrFail();

        return Download::pdf('pdf.invoice', InvoiceDocument::data($invoice), 'factura-'.$invoice->code.'.pdf');
    }

    /** Pago simulado: el mismo camino que un webhook de la pasarela. */
    private function settleFake(Order $order, bool $ok, StartPayment $start): bool
    {
        abort_unless(app(PaymentGateway::class)->name() === 'fake' && ! app()->isProduction(), 404);

        $payment = $start($order->id, (string) $order->reference, $order->total_cents, $order->currency, $order->email);
        app(HandleGatewayEvent::class)('fake', new GatewayEvent(
            eventId: 'fake:'.Str::uuid()->toString(),
            type: $ok ? GatewayEvent::SUCCEEDED : GatewayEvent::FAILED,
            providerReference: $payment->providerReference,
            amountCents: $order->total_cents,
            currency: $order->currency,
            failureMessage: $ok ? null : 'Pago simulado rechazado.',
        ));

        return $ok;
    }

    /** Salir de «Finalizar compra» (al carrito o a un pedido ya pagado), también desde JSON. */
    private function leave(Request $request, string $url, ?string $error = null): JsonResponse|RedirectResponse
    {
        if ($error !== null) {
            $request->session()->flash('cart.error', $error);
        }

        return $request->expectsJson() ? response()->json(['redirect' => $url, 'message' => $error], 409) : redirect($url);
    }

    /** Huella del carrito, los datos y el envío: si no cambia, se reutiliza el pedido pendiente. */
    private function fingerprint(PricedCart $cart, CheckoutDetails $details, ShippingQuote $quote): string
    {
        $lines = array_map(fn ($l): array => [$l->variant->variantId, $l->quantity, $l->totalCents()], $cart->buyableLines());

        return hash('sha256', (string) json_encode([$lines, (array) $details, $quote->priceCents, $quote->zone->value]));
    }

    private function priced(Request $request): PricedCart
    {
        return ($this->price)($this->cart->lines(), StoreAccess::isStaff($request->user()));
    }

    /**
     * @return array{0: ?ShippingQuote, 1: ?string}
     */
    private function quoteFor(PricedCart $cart, string $country, string $postalCode): array
    {
        $zone = $this->shipping->zoneFor($country, $postalCode);
        if ($zone === null || in_array($zone->value, (array) config('clavedealma.shop.blocked_zones', []), true)) {
            return [null, $zone === null ? 'Todavía no enviamos a ese país.' : 'Todavía no enviamos a '.$zone->label().'.'];
        }

        $quote = $this->shipping->quote($zone, $cart->weightGrams(), $cart->subtotalCents());

        return $quote === null ? [null, 'Todavía no enviamos a '.$zone->label().'.'] : [$quote, null];
    }

    /** Compra de prueba: pago simulado, o el equipo comprando en una tienda sin publicar. */
    private function isTest(Request $request, PricedCart $cart): bool
    {
        if (app(PaymentGateway::class)->name() === 'fake') {
            return true;
        }
        foreach ($cart->buyableLines() as $line) {
            if (! $line->store->sells()) {
                return true;
            }
        }

        return false;
    }

    private function order(string $token): Order
    {
        return Order::query()->with('lines')->where('token', $token)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Order $order): array
    {
        $m = fn (int $c): string => Money::fromCents($c)->format();

        return [
            'order' => $order,
            'items' => $order->lines->map(fn ($l): array => [
                'name' => $l->product_name,
                'option' => $l->label(),
                'store' => $l->store_name,
                'quantity' => $l->quantity,
                'total' => $m($l->total_cents),
                'image' => $l->image_path === null ? null : Storage::disk('public')->url($l->image_path),
            ])->all(),
            'subtotal' => $m($order->subtotal_cents),
            'shippingPrice' => $order->shipping_free ? 'Gratis' : $m($order->shipping_cents),
            'total' => $m($order->total_cents),
            'vat' => $m($order->vat_cents),
        ];
    }
}
