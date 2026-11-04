<?php

use App\Filament\Resources\Returns\Pages\ViewReturn;
use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Infrastructure\Models\StockLevel;
use App\Modules\Orders\Application\AdvanceOrder;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Contracts\Refunds;
use App\Modules\Returns\Contracts\ReturnStatus;
use App\Modules\Returns\Domain\ReturnPolicy;
use App\Modules\Returns\Infrastructure\Mail\NewReturnAlert;
use App\Modules\Returns\Infrastructure\Mail\ReturnUpdateMail;
use App\Modules\Returns\Infrastructure\Models\ReturnRequest;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Store;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
    $this->withoutVite();
    config()->set('payments.driver', 'fake');
    config()->set('clavedealma.superadmin.email', 'equipo@clavedealma.test');
    config()->set('clavedealma.returns.address', 'Calle del Taller 5, 41001 Sevilla');

    $store = Store::factory()->create(['status' => StoreStatus::Published]);
    $tee = Product::factory()->create(['store_id' => $store->id, 'name' => 'La esencia', 'status' => ProductStatus::Published, 'price_cents' => 3000]);
    $cap = Product::factory()->create(['store_id' => $store->id, 'name' => 'Gorra nombre', 'status' => ProductStatus::Published, 'price_cents' => 2500, 'personalized' => true]);
    $this->tee = $tee->variants()->create(['sku' => 'TEE-M', 'size' => 'M']);
    $this->cap = $cap->variants()->create(['sku' => 'CAP-U']);
    StockLevel::where('product_variant_id', $this->tee->id)->update(['mode' => StockMode::LimitedThenOnDemand, 'quantity' => 5]);

    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::value('id'), 'active' => true, 'max_days' => 3]);
    $rate->bands()->create(['max_weight_grams' => 5000, 'price_cents' => 500]);

    $this->postJson(route('cart.add'), ['variant_id' => $this->tee->id, 'quantity' => 2]);
    $this->postJson(route('cart.add'), ['variant_id' => $this->cap->id]);
    $this->post(route('checkout.place'), [
        'email' => 'ana@example.com', 'name' => 'Ana Pérez', 'phone' => '600123456', 'address_line1' => 'Calle Mayor 1',
        'postal_code' => '28013', 'city' => 'Madrid', 'country' => 'ES', 'terms' => '1',
    ]);
    $this->order = Order::query()->sole();
    $this->post(route('checkout.simulate', $this->order->token), ['outcome' => 'ok']);

    $advance = app(AdvanceOrder::class);
    $advance->startProduction($this->order->refresh());
    $advance->markReady($this->order->refresh());
    $advance->ship($this->order->refresh(), Carrier::value('name'), 'TRK1');
    $this->teeLine = $this->order->lines()->where('sku', 'TEE-M')->sole();
    $this->capLine = $this->order->lines()->where('sku', 'CAP-U')->sole();
    Filament::setCurrentPanel('admin');
});

it('reglas: plazo de 14 días e importe con el envío solo si vuelve todo', function () {
    $delivered = new DateTimeImmutable('2026-11-10 10:00');
    expect(ReturnPolicy::deadline($delivered, null, 3)?->format('Y-m-d'))->toBe('2026-11-24')
        ->and(ReturnPolicy::deadline(null, new DateTimeImmutable('2026-11-10'), 3)?->format('Y-m-d'))->toBe('2026-11-27')
        ->and(ReturnPolicy::deadline(null, null, 3))->toBeNull();

    $lines = [['id' => 1, 'quantity' => 2, 'unit_price_cents' => 3000]];
    expect(ReturnPolicy::refundCents($lines, [], [1 => 1], 500))->toBe(3000)
        ->and(ReturnPolicy::refundCents($lines, [1 => 1], [1 => 1], 500))->toBe(3500)
        ->and(ReturnPolicy::returnable([['id' => 1, 'quantity' => 2, 'personalized' => false], ['id' => 2, 'quantity' => 1, 'personalized' => true]], [1 => 1]))->toBe([1 => 1, 2 => 0]);
});

it('el cliente pide la devolución desde su pedido; las personalizadas no se pueden', function () {
    $this->get(route('checkout.thanks', $this->order->token))->assertSee('Devolver piezas');
    $this->get(route('returns.create', $this->order->token))->assertOk()
        ->assertSee('La esencia')->assertSee('Pieza personalizada: no admite devolución');

    $this->post(route('returns.store', $this->order->token), ['quantities' => [$this->capLine->id => 1], 'reason' => 'size'])
        ->assertSessionHasErrors('quantities');

    $this->post(route('returns.store', $this->order->token), ['quantities' => [$this->teeLine->id => 1], 'reason' => 'size', 'comment' => 'Me queda grande'])
        ->assertRedirect(route('checkout.thanks', $this->order->token));

    $return = ReturnRequest::query()->sole();
    expect($return->reference)->toMatch('/^DEV-\d{2}-\d{6}$/')
        ->and($return->status)->toBe(ReturnStatus::Requested)
        ->and($return->refund_cents)->toBe(3000)
        ->and($return->includes_shipping)->toBeFalse();
    Mail::assertSent(ReturnUpdateMail::class, fn ($m) => $m->hasTo('ana@example.com') && $m->kind === ReturnUpdateMail::REQUESTED);
    Mail::assertSent(NewReturnAlert::class, fn ($m) => $m->hasTo('equipo@clavedealma.test'));
    $this->get(route('checkout.thanks', $this->order->token))->assertSee($return->reference)->assertSee('La estamos revisando');

    // No se puede pedir más de lo que queda.
    $this->post(route('returns.store', $this->order->token), ['quantities' => [$this->teeLine->id => 2], 'reason' => 'size'])
        ->assertSessionHasErrors('quantities');
});

it('pasado el plazo ya no se puede devolver', function () {
    app(AdvanceOrder::class)->markDelivered($this->order->refresh());
    $this->travel(15)->days();

    $this->get(route('returns.create', $this->order->token))->assertRedirect(route('checkout.thanks', $this->order->token));
    $this->post(route('returns.store', $this->order->token), ['quantities' => [$this->teeLine->id => 1], 'reason' => 'size'])
        ->assertSessionHasErrors('quantities');
});

it('el equipo aprueba, recibe, repone el stock y devuelve el dinero', function () {
    $this->post(route('returns.store', $this->order->token), ['quantities' => [$this->teeLine->id => 2], 'reason' => 'changed_mind']);
    $return = ReturnRequest::query()->sole();
    $stockBefore = StockLevel::where('product_variant_id', $this->tee->id)->value('quantity');

    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create(['name' => 'Admin']));
    Livewire::test(ViewReturn::class, ['record' => $return->getRouteKey()])
        ->assertActionVisible('approve')->assertActionHidden('receive')
        ->mountAction('approve')
        ->assertSchemaStateSet(['instructions' => ViewReturn::defaultInstructions($return)], 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasNoActionErrors();
    expect($return->refresh()->status)->toBe(ReturnStatus::Approved)
        ->and($return->instructions)->toContain('Calle del Taller 5');
    Mail::assertSent(ReturnUpdateMail::class, fn ($m) => $m->kind === ReturnUpdateMail::APPROVED);

    Livewire::test(ViewReturn::class, ['record' => $return->getRouteKey()])
        ->callAction('receive', ['restock' => true])->assertHasNoActionErrors();

    $return->refresh();
    expect($return->status)->toBe(ReturnStatus::Refunded)
        ->and($return->refund_cents)->toBe(6000)
        ->and(app(Refunds::class)->refundedCents($this->order->id))->toBe(6000)
        ->and(StockLevel::where('product_variant_id', $this->tee->id)->value('quantity'))->toBe($stockBefore + 2)
        ->and($this->order->events()->pluck('type')->all())->toContain('return_requested', 'return_approved', 'return_received', 'refunded');
    Mail::assertSent(ReturnUpdateMail::class, fn ($m) => $m->kind === ReturnUpdateMail::REFUNDED);
});

it('rechazar libera las piezas para otra solicitud', function () {
    $this->post(route('returns.store', $this->order->token), ['quantities' => [$this->teeLine->id => 2], 'reason' => 'size']);
    $return = ReturnRequest::query()->sole();

    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
    Livewire::test(ViewReturn::class, ['record' => $return->getRouteKey()])
        ->callAction('reject', ['reason' => 'Las piezas están usadas'])->assertHasNoActionErrors();

    expect($return->refresh()->status)->toBe(ReturnStatus::Rejected);
    Mail::assertSent(ReturnUpdateMail::class, fn ($m) => $m->kind === ReturnUpdateMail::REJECTED);
    $this->post(route('returns.store', $this->order->token), ['quantities' => [$this->teeLine->id => 2], 'reason' => 'size'])->assertSessionHasNoErrors();
});

it('las devoluciones son inmutables y solo las ve el superadministrador', function () {
    $this->post(route('returns.store', $this->order->token), ['quantities' => [$this->teeLine->id => 1], 'reason' => 'size']);
    $return = ReturnRequest::query()->sole();

    expect(fn () => $return->update(['refund_cents' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $return->delete())->toThrow(LogicException::class);

    $this->actingAs(User::factory()->withRole(Role::CatalogManager)->create());
    $this->get('/admin/devoluciones')->assertForbidden();
    $this->actingAs(User::factory()->withRole(Role::Manufacturer)->create());
    $this->get('/admin/devoluciones')->assertForbidden();
});
