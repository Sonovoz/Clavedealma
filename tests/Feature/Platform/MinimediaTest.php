<?php

use App\Filament\Pages\Minimedia;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\Refund;
use App\Modules\Stores\Infrastructure\Models\Store;
use App\Platform\Minimedia\CloseMinimediaMonth;
use App\Platform\Minimedia\MinimediaCommission;
use App\Platform\Minimedia\MinimediaStatement;
use App\Platform\Minimedia\MinimediaStatementMail;
use App\Platform\Security\SecurityEvent;
use App\Platform\Security\SecurityEventType;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;

/** Pedido cobrado con una línea (IVA 21 %) y, si se pide, envío. */
function pedidoMinimedia(Store $store, int $goodsCents, string $paidAt, int $shippingCents = 0, bool $test = false): Order
{
    $order = Order::query()->create([
        'token' => Str::random(48), 'status' => OrderStatus::Paid, 'is_test' => $test, 'email' => 'cliente@example.com',
        'customer_name' => 'Cliente', 'phone' => '600000000', 'address_line1' => 'Calle 1', 'postal_code' => '28001',
        'city' => 'Madrid', 'country' => 'ES', 'shipping_zone' => 'peninsula', 'shipping_carrier' => 'NACEX', 'shipping_cents' => $shippingCents,
        'delivery_min_days' => 1, 'delivery_max_days' => 2, 'lead_time_min_days' => 5, 'lead_time_max_days' => 7, 'weight_grams' => 300,
        'subtotal_cents' => $goodsCents, 'total_cents' => $goodsCents + $shippingCents, 'vat_cents' => 0, 'terms_version' => 'v1', 'terms_accepted_at' => $paidAt,
        'paid_at' => $paidAt,
    ]);
    $order->lines()->create([
        'store_id' => $store->id, 'store_name' => $store->name, 'product_id' => 1, 'product_variant_id' => 1, 'sku' => 'SKU', 'product_name' => 'Camiseta',
        'unit_price_cents' => $goodsCents, 'quantity' => 1, 'total_cents' => $goodsCents, 'vat_rate_bps' => 2100, 'commission_bps' => 2500, 'weight_grams' => 300, 'personalized' => false,
    ]);

    return $order;
}

function devolucionMinimedia(Order $order, int $amountCents, string $when): void
{
    $payment = Payment::query()->firstOrCreate(['order_id' => $order->id], [
        'provider' => 'redsys', 'provider_reference' => 'R'.$order->id, 'amount_cents' => $order->total_cents, 'currency' => 'EUR', 'status' => PaymentStatus::Succeeded,
    ]);
    $refund = Refund::query()->create([
        'payment_id' => $payment->id, 'order_id' => $order->id, 'amount_cents' => $amountCents, 'idempotency_key' => Str::uuid()->toString(),
        'status' => Refund::SUCCEEDED, 'reason' => 'Prueba',
    ]);
    $refund->forceFill(['created_at' => $when])->saveQuietly();
}

beforeEach(function () {
    Mail::fake();
    $this->a = Store::factory()->create(['name' => 'Luna Bruma']);
    $this->b = Store::factory()->create(['name' => 'Farruquito']);

    pedidoMinimedia($this->a, 12100, '2026-10-05 10:00:00');                       // 100 € sin IVA
    pedidoMinimedia($this->b, 6050, '2026-10-20 10:00:00');                        // 50 € sin IVA
    pedidoMinimedia($this->a, 30250, '2026-10-21 10:00:00', test: true);           // prueba: no cuenta
    $september = pedidoMinimedia($this->a, 12100, '2026-09-28 10:00:00');          // de septiembre…
    devolucionMinimedia($september, 6050, '2026-10-10 10:00:00');                  // …devuelto en parte en octubre: −50 €
    $cancelled = pedidoMinimedia($this->b, 6050, '2026-10-22 10:00:00', 495);      // 50 € + envío…
    devolucionMinimedia($cancelled, 6545, '2026-10-23 10:00:00');                  // …cancelado entero: −50 € (el envío no cuenta)
    pedidoMinimedia($this->a, 12100, '2026-11-02 10:00:00');                       // noviembre: no entra en octubre
});

it('calcula el 6 % sobre lo vendido sin IVA ni envío, menos lo devuelto en el mes', function () {
    $r = MinimediaCommission::compute('2026-10', 600);

    expect($r)->toMatchArray(['label' => 'Octubre de 2026', 'orders' => 3, 'sales_base' => 20000, 'refunds_base' => 10000, 'net_base' => 10000, 'commission' => 600])
        ->and(collect($r['stores'])->keyBy('store')->map(fn (array $s): array => [$s['orders'], $s['sales_base'], $s['refunds_base'], $s['commission']])->all())
        ->toBe(['Farruquito' => [2, 10000, 5000, 300], 'Luna Bruma' => [1, 10000, 5000, 300]]);
});

it('el día 1 cierra el mes anterior y lo envía a info@minimedia.es con PDF y CSV, una sola vez', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 08:20:00', 'Europe/Madrid'));

    expect(Artisan::call('minimedia:cierre'))->toBe(0);
    $statement = MinimediaStatement::query()->sole();
    expect($statement)->month->toBe('2026-10')->commission_cents->toBe(600)->sent_to->toBe('info@minimedia.es');

    Mail::assertSent(MinimediaStatementMail::class, function (MinimediaStatementMail $mail): bool {
        $names = array_map(fn ($a) => $a->as, $mail->attachments());

        return $mail->hasTo('info@minimedia.es') && $names === ['minimedia-2026-10.pdf', 'minimedia-2026-10.csv']
            && str_contains($mail->envelope()->subject, 'octubre de 2026: 6,00 €');
    });
    expect(CloseMinimediaMonth::csv($statement))->toContain('"TOTAL";"3";"200,00";"100,00";"100,00";"6 %";"6,00"');

    // Al día siguiente no se repite.
    $this->travel(1)->days();
    Artisan::call('minimedia:cierre');
    Mail::assertSentCount(1);
});

it('un mes cerrado no cambia: una devolución posterior cuenta en el mes en que se hace', function () {
    $statement = app(CloseMinimediaMonth::class)->close('2026-10');
    devolucionMinimedia(Order::query()->where('paid_at', '2026-10-05 10:00:00')->sole(), 12100, '2026-11-03 10:00:00');

    expect(app(CloseMinimediaMonth::class)->close('2026-10')->commission_cents)->toBe(600)
        ->and(MinimediaCommission::compute('2026-11', 600)['refunds_base'])->toBe(10000)
        ->and(fn () => $statement->update(['commission_cents' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $statement->delete())->toThrow(LogicException::class);
});

it('si el email falla, el cierre se guarda y se reintenta al día siguiente', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 08:20:00', 'Europe/Madrid'));
    // Correo real hacia un servidor SMTP que no responde.
    Mail::swap(new MailManager(app()));
    config()->set('mail.default', 'smtp');
    config()->set('mail.mailers.smtp', ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 9, 'timeout' => 1]);

    expect(Artisan::call('minimedia:cierre'))->toBe(1);
    expect(MinimediaStatement::query()->sole())->sent_at->toBeNull()->send_error->not->toBeNull();
    $this->get(Minimedia::getUrl()); // (la página avisa: se comprueba en la prueba del panel)

    Mail::fake();
    $this->travel(1)->days();
    expect(Artisan::call('minimedia:cierre'))->toBe(0)
        ->and(MinimediaStatement::query()->sole()->sent_at)->not->toBeNull();
});

it('en el panel: lo que va del mes, los cierres y los ajustes (solo el superadmin)', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'Europe/Madrid'));
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());

    $this->get(Minimedia::getUrl())->assertOk()
        ->assertSee('Noviembre de 2026, hasta hoy')->assertSee('info@minimedia.es')->assertSee('6 %')->assertSee('Cerrar octubre de 2026');

    Livewire::test(Minimedia::class)->callAction('closePrevious')->assertNotified('Octubre de 2026 cerrado y enviado');
    Mail::assertSent(MinimediaStatementMail::class);

    Livewire::test(Minimedia::class)
        ->callAction('settings', ['rate' => '6,5', 'email' => 'cuentas@minimedia.es', 'current_password' => 'mal'])->assertHasActionErrors(['current_password']);
    Livewire::test(Minimedia::class)
        ->callAction('settings', ['rate' => '6,5', 'email' => 'cuentas@minimedia.es', 'current_password' => 'password'])->assertHasNoActionErrors();

    expect(MinimediaCommission::rateBps())->toBe(650)->and(MinimediaCommission::email())->toBe('cuentas@minimedia.es')
        ->and(MinimediaStatement::query()->sole()->rate_bps)->toBe(600)
        ->and(SecurityEvent::query()->where('type', SecurityEventType::MinimediaChanged)->count())->toBe(2);

    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());
    $this->get(Minimedia::getUrl())->assertForbidden();
});
