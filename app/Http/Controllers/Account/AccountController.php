<?php

namespace App\Http\Controllers\Account;

use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Storefront\Account\CustomerOrders;
use App\Storefront\Checkout\OrderPage;
use App\Storefront\Seo\SeoBuilder;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * «Mi cuenta» como WooCommerce: Escritorio, Pedidos, Ver pedido, Facturas y Devoluciones
 * (ADR 0021). Solo lectura: los pedidos no se tocan desde aquí.
 */
class AccountController
{
    public function __construct(private readonly SeoBuilder $seo) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        if (! $user instanceof User) {
            // «¿Ya eres cliente? Haz clic aquí para acceder» en «Finalizar compra» vuelve allí.
            if ($request->query('volver') === 'finalizar-compra') {
                $request->session()->put('url.intended', route('checkout.show'));
            }

            return view('account.login', ['seo' => $this->seo->checkout('Mi cuenta')]);
        }

        return view('account.dashboard', [
            'seo' => $this->seo->checkout('Mi cuenta'),
            'user' => $user,
            'recent' => $this->rows(CustomerOrders::query($user)->latest('id')->limit(3)->get()),
            'waiting' => CustomerOrders::waitingForVerification($user),
        ]);
    }

    public function orders(Request $request): View
    {
        $user = $this->user($request);
        $orders = CustomerOrders::query($user)->with('lines')->latest('id')->paginate(10);

        return view('account.orders', [
            'seo' => $this->seo->checkout('Pedidos'),
            'orders' => $orders,
            'rows' => $this->rows($orders->getCollection()),
            'waiting' => CustomerOrders::waitingForVerification($user),
        ]);
    }

    public function order(Request $request, string $reference): View
    {
        $order = CustomerOrders::query($this->user($request))->with('lines')->where('reference', $reference)->firstOrFail();

        return view('account.order', OrderPage::data($order) + [
            'seo' => $this->seo->checkout('Pedido '.$order->reference),
        ]);
    }

    public function invoices(Request $request): View
    {
        $orders = CustomerOrders::query($this->user($request))->get(['id', 'reference', 'token'])->keyBy('id');
        $invoices = Invoice::query()->whereIn('order_id', $orders->keys())->latest('id')->get()
            ->map(fn (Invoice $i): array => [
                'code' => $i->code,
                'label' => $i->type->label(),
                'date' => $i->issued_at->timezone('Europe/Madrid')->format('d/m/Y'),
                'order' => $orders[$i->order_id]->reference ?? '',
                'total' => Money::fromCents($i->total_cents)->format(),
                'url' => route('checkout.invoice', [$orders[$i->order_id]->token ?? '', $i->code]),
            ])->all();

        return view('account.invoices', ['seo' => $this->seo->checkout('Facturas'), 'invoices' => $invoices]);
    }

    public function returns(Request $request): View
    {
        $orders = CustomerOrders::query($this->user($request))->get(['id', 'reference'])->keyBy('id');
        $returns = ReturnRequest::query()->whereIn('order_id', $orders->keys())->latest('id')->get()
            ->map(fn (ReturnRequest $r): array => [
                'reference' => $r->reference,
                'order' => $orders[$r->order_id]->reference ?? '',
                'date' => $r->created_at->timezone('Europe/Madrid')->format('d/m/Y'),
                'status' => $r->status->customerLabel(),
                'amount' => Money::fromCents($r->refund_cents)->format(),
            ])->all();

        return view('account.returns', ['seo' => $this->seo->checkout('Devoluciones'), 'returns' => $returns]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Filas de la tabla «Pedidos», como WooCommerce: pedido, fecha, estado, total y acciones.
     *
     * @param  iterable<Order>  $orders
     * @return list<array<string, mixed>>
     */
    private function rows(iterable $orders): array
    {
        $rows = [];
        foreach ($orders as $order) {
            $units = (int) $order->lines()->sum('quantity');
            $rows[] = [
                'reference' => $order->reference,
                'date' => $order->created_at->timezone('Europe/Madrid')->translatedFormat('j \d\e F \d\e Y'),
                'status' => $order->status->wooLabel(),
                'statusKey' => $order->status->value,
                'total' => Money::fromCents($order->total_cents)->format().' para '.$units.' '.($units === 1 ? 'artículo' : 'artículos'),
                'view' => route('account.order', $order->reference),
                'pay' => $order->status === OrderStatus::PendingPayment ? route('checkout.pay', $order->token) : null,
            ];
        }

        return $rows;
    }
}
