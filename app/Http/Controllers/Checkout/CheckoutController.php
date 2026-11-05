<?php

namespace App\Http\Controllers\Checkout;

use App\Http\Requests\CheckoutRequest;
use App\Modules\Cart\Application\CartStore;
use App\Modules\Cart\Application\PriceCart;
use App\Modules\Cart\Contracts\PricedCart;
use App\Modules\Identity\Application\RegisterCustomer;
use App\Modules\Identity\Infrastructure\Models\CustomerAddress;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Application\CancelPendingOrder;
use App\Modules\Orders\Application\PlaceOrder;
use App\Modules\Orders\Contracts\CheckoutDetails;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Application\HandleGatewayEvent;
use App\Modules\Payments\Application\StartPayment;
use App\Modules\Payments\Application\SyncOrderPayments;
use App\Modules\Payments\Contracts\GatewayEvent;
use App\Modules\Payments\Contracts\InvalidWebhook;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Contracts\PaymentUnavailable;
use App\Modules\Shipping\Contracts\ShippingQuote;
use App\Modules\Shipping\Contracts\ShippingQuoter;
use App\Platform\Documents\Download;
use App\Platform\Documents\InvoiceDocument;
use App\Storefront\Checkout\CartDiscount;
use App\Storefront\Checkout\CartPresenter;
use App\Storefront\Checkout\Countries;
use App\Storefront\Checkout\OrderPage;
use App\Storefront\Seo\SeoBuilder;
use App\Storefront\StoreAccess;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * «Finalizar compra» como WooCommerce: datos, «Tu pedido» y pago en una sola página,
 * y después «Pedido recibido». Todo importe se calcula en el servidor; el navegador solo
 * elige variantes y escribe la dirección. Con Redsys (ADR 0026) el cliente elige el método
 * de pago (tarjeta o Bizum) y paga en la página segura de Redsys; con Stripe, la página crea
 * el pedido y confirma el pago sin salir de ella.
 */
class CheckoutController
{
    public function __construct(
        private readonly CartStore $cart,
        private readonly PriceCart $price,
        private readonly ShippingQuoter $shipping,
        private readonly SeoBuilder $seo,
        private readonly CartDiscount $discount,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $priced = $this->priced($request);
        if ($priced->isEmpty() || $priced->hasProblems()) {
            return to_route('cart.show');
        }

        $gateway = app(PaymentGateway::class);
        $user = $request->user();
        $coupon = $this->discount->current($priced);
        if ($coupon !== null && ! $coupon->ok()) {
            $request->session()->now('payment.error', $coupon->error);
        }
        $row = CartDiscount::row($coupon);

        return view('checkout.details', [
            'coupon' => $row,
            'totalWithoutShipping' => Money::fromCents($priced->subtotalCents() - ($row['cents'] ?? 0))->format(),
            'prefill' => $user instanceof User ? $this->prefill($user) : [],
            'customer' => $user instanceof User ? $user : null,
            'seo' => $this->seo->checkout('Finalizar compra'),
            'lines' => CartPresenter::lines($priced),
            'subtotal' => Money::fromCents($priced->subtotalCents())->format(),
            'subtotalCents' => max(50, $priced->subtotalCents() - ($row['cents'] ?? 0)),
            'countries' => Countries::ALL,
            'gateway' => $gateway->name(),
            'publicKey' => $gateway->publicKey(),
            'methods' => $gateway->methods(),
            'testMode' => $gateway->testMode(),
        ]);
    }

    /** Precio del envío para la dirección que se está escribiendo (lo pide la página). */
    public function quote(Request $request): JsonResponse
    {
        $priced = $this->priced($request);
        [$quote, $error] = $this->quoteFor($priced, (string) $request->query('country', 'ES'), (string) $request->query('postal_code', ''));
        $coupon = $this->discount->current($priced);
        $off = $coupon !== null && $coupon->ok() ? $coupon->discountCents() : 0;
        $quote = $quote === null ? null : CartDiscount::shipping($quote, $coupon);

        return response()->json($quote === null
            ? ['ok' => false, 'message' => $error]
            : [
                'ok' => true,
                'shipping' => $quote->free ? 'Gratis' : Money::fromCents($quote->priceCents)->format(),
                'total' => Money::fromCents($priced->subtotalCents() - $off + $quote->priceCents)->format(),
                'total_cents' => $priced->subtotalCents() - $off + $quote->priceCents,
                'delivery' => $quote->deliveryLabel(),
                'zone' => $quote->zone->label(),
            ]);
    }

    /**
     * «Realizar el pedido». Con Stripe (petición JSON) devuelve el cobro para confirmarlo en
     * la misma página; con el pago simulado lo resuelve aquí; sin JavaScript, lleva a pagar.
     */
    public function place(CheckoutRequest $request, PlaceOrder $place, StartPayment $start, SyncOrderPayments $sync, CancelPendingOrder $cancel, RegisterCustomer $register): JsonResponse|RedirectResponse|View
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

        // «¿Crear una cuenta?» (como WooCommerce): se crea, se inicia la sesión y el pedido queda en ella.
        $user = $request->user();
        if (! $user instanceof User && $request->boolean('create_account')) {
            try {
                $user = $register($details->email, (string) $request->input('account_password'), $details->name);
            } catch (InvalidArgumentException $e) {
                return $request->expectsJson()
                    ? response()->json(['message' => $e->getMessage(), 'errors' => ['email' => [$e->getMessage()]]], 422)
                    : back()->withInput($request->except('account_password'))->withErrors(['email' => $e->getMessage()]);
            }
            Auth::login($user);
            $request->session()->regenerate();
        }
        $details = $request->details($user instanceof User ? $user->id : null);

        // Cupón (ADR 0024): se vuelve a comprobar con el email (límite por cliente).
        $coupon = $this->discount->current($priced, $details->email);
        if ($coupon !== null && ! $coupon->ok()) {
            return $request->expectsJson()
                ? response()->json(['message' => $coupon->error, 'redirect' => route('checkout.show')], 409)
                : to_route('checkout.show')->withInput()->with('payment.error', $coupon->error);
        }
        $quote = CartDiscount::shipping($quote, $coupon);

        // Como WooCommerce: si el cliente vuelve a pulsar «Realizar el pedido» con el mismo
        // carrito y los mismos datos, se reutiliza el pedido que espera el pago. Si ha cambiado
        // algo, el anterior se anula (y su cobro) para que no queden dos pedidos pagables.
        $fingerprint = $this->fingerprint($priced, $details, $quote, $coupon?->code, $coupon->discounts ?? []);
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
                $order = $this->order($place($priced, $details, $quote, $this->isTest($request, $priced), CartDiscount::forOrder($coupon))->token);
            } catch (InvalidArgumentException $e) {
                return $this->leave($request, route('cart.show'), $e->getMessage());
            }
            $request->session()->push('checkout.orders', $order->token);
            $request->session()->put('checkout.pending', ['token' => $order->token, 'fingerprint' => $fingerprint]);
            // Como WooCommerce: la dirección del pedido pasa a ser la guardada en «Mi cuenta».
            if ($user instanceof User) {
                CustomerAddress::query()->updateOrCreate(['user_id' => $user->id], [
                    'name' => $details->name, 'phone' => $details->phone, 'address_line1' => $details->addressLine1,
                    'address_line2' => $details->addressLine2, 'postal_code' => $details->postalCode, 'city' => $details->city,
                    'province' => $details->province, 'country' => $details->country,
                ]);
            }
        }

        $gateway = app(PaymentGateway::class);
        if ($gateway->name() === 'fake' && $request->filled('simulate')) {
            return $this->settleFake($order, $request->input('simulate') === 'ok', $start)
                ? to_route('checkout.thanks', $order->token)
                : to_route('checkout.show')->withInput()->with('payment.error', 'El pago se ha rechazado. Puedes intentarlo de nuevo.');
        }

        // Redsys: a su página de pago segura con el método elegido.
        if ($gateway->methods() !== []) {
            return $request->expectsJson()
                ? response()->json(['redirect' => route('checkout.pay', $order->token)], 409)
                : $this->toGateway($order, $start, (string) $request->input('payment_method'));
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

    public function pay(Request $request, string $token, StartPayment $start, PaymentGateway $gateway): View|RedirectResponse
    {
        $order = $this->order($token);
        // Vuelta de Redsys sin pagar (cancelado o rechazado): se apunta el motivo.
        $returned = $this->absorbReturn($request);
        $order->refresh();
        if ($order->status !== OrderStatus::PendingPayment) {
            return to_route('checkout.thanks', $order->token);
        }
        if ($returned?->type === GatewayEvent::FAILED) {
            $request->session()->now('payment.error', 'No se ha completado el pago: '.rtrim((string) $returned->failureMessage, '.').'. Puedes intentarlo de nuevo.');
        }

        $payment = null;
        if ($gateway->methods() === []) {
            try {
                $payment = $start($order->id, (string) $order->reference, $order->total_cents, $order->currency, $order->email);
            } catch (PaymentUnavailable $e) {
                report($e);
            }
        }

        return view('checkout.pay', OrderPage::summary($order) + [
            'seo' => $this->seo->checkout('Pagar el pedido'),
            'gateway' => $gateway->name(),
            'publicKey' => $gateway->publicKey(),
            'clientSecret' => $payment?->clientSecret,
            'unavailable' => $payment === null && $gateway->methods() === [],
            'returnUrl' => route('checkout.thanks', $order->token),
            'methods' => $gateway->methods(),
            'testMode' => $gateway->testMode(),
        ]);
    }

    /** «Pagar el pedido» con Redsys: al TPV con el método elegido. */
    public function payStart(Request $request, string $token, StartPayment $start, PaymentGateway $gateway): View|RedirectResponse
    {
        $order = $this->order($token);
        if ($order->status !== OrderStatus::PendingPayment) {
            return to_route('checkout.thanks', $order->token);
        }
        abort_if($gateway->methods() === [], 404);
        $request->validate(['payment_method' => ['required', 'in:'.implode(',', array_keys($gateway->methods()))]], ['payment_method.*' => 'Elige un método de pago.']);

        return $this->toGateway($order, $start, (string) $request->input('payment_method'));
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
        if ($order->status === OrderStatus::PendingPayment && $this->absorbReturn($request) !== null) {
            $order->refresh();
        }
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

        return view('checkout.thanks', OrderPage::data($order) + [
            'seo' => $this->seo->checkout($order->status === OrderStatus::Paid ? 'Pedido recibido' : 'Pedido '.$order->reference),
        ]);
    }

    /** Factura en PDF, desde el enlace privado del pedido. */
    public function invoice(string $token, string $code): StreamedResponse
    {
        $order = $this->order($token);
        $invoice = Invoice::query()->where('order_id', $order->id)->where('code', $code)->firstOrFail();

        return Download::pdf('pdf.invoice', InvoiceDocument::data($invoice), 'factura-'.$invoice->code.'.pdf');
    }

    /**
     * Datos para rellenar «Finalizar compra» a un cliente con sesión.
     *
     * @return array<string, ?string>
     */
    private function prefill(User $user): array
    {
        $a = $user->address()->first();

        return ['email' => $user->email, 'name' => $a->name ?? $user->name] + ($a === null ? [] : [
            'phone' => $a->phone, 'address_line1' => $a->address_line1, 'address_line2' => $a->address_line2,
            'postal_code' => $a->postal_code, 'city' => $a->city, 'province' => $a->province, 'country' => $a->country,
        ]);
    }

    /**
     * Página «Te llevamos al pago seguro»: el navegador envía el cobro firmado a Redsys.
     * Si el TPV no responde, el pedido queda guardado en «Pagar el pedido».
     */
    private function toGateway(Order $order, StartPayment $start, string $method): View|RedirectResponse
    {
        try {
            $payment = $start($order->id, (string) $order->reference, $order->total_cents, $order->currency, $order->email, $method,
                route('checkout.thanks', $order->token), route('checkout.pay', $order->token));
        } catch (PaymentUnavailable $e) {
            report($e);

            return to_route('checkout.pay', $order->token)->with('payment.error', 'Ahora mismo no podemos conectar con el sistema de pago. Tu pedido está guardado: vuelve a intentarlo en unos minutos.');
        }

        return view('checkout.redirect', OrderPage::summary($order) + [
            'seo' => $this->seo->checkout('Pago seguro'),
            'action' => (string) $payment->redirectUrl,
            'fields' => $payment->redirectFields,
            'method' => app(PaymentGateway::class)->methods()[$method] ?? null,
        ]);
    }

    /**
     * Resultado firmado que trae el cliente al volver de Redsys: el mismo camino que el aviso
     * de la pasarela (vale aunque el aviso no llegue, como en local). Uno falso se ignora.
     */
    private function absorbReturn(Request $request): ?GatewayEvent
    {
        $gateway = app(PaymentGateway::class);
        try {
            $event = $gateway->returnEvent($request->query());
        } catch (InvalidWebhook $e) {
            Log::warning('Vuelta del pago con firma no válida: '.$e->getMessage());

            return null;
        }
        if ($event !== null) {
            app(HandleGatewayEvent::class)($gateway->name(), $event);
        }

        return $event;
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
    /**
     * @param  array<int, int>  $discounts
     */
    private function fingerprint(PricedCart $cart, CheckoutDetails $details, ShippingQuote $quote, ?string $coupon = null, array $discounts = []): string
    {
        $lines = array_map(fn ($l): array => [$l->variant->variantId, $l->quantity, $l->totalCents()], $cart->buyableLines());

        return hash('sha256', (string) json_encode([$lines, (array) $details, $quote->priceCents, $quote->zone->value, $coupon, $discounts]));
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
        // Pago simulado o entorno de pruebas de la pasarela (Redsys «test», Stripe sk_test_).
        if (app(PaymentGateway::class)->testMode()) {
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
}
