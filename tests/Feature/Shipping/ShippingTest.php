<?php

use App\Filament\Resources\ShippingRates\Pages\EditShippingRate;
use App\Modules\Identity\Contracts\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Shipping\Application\QuoteShipping;
use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Infrastructure\Models\Carrier;
use App\Modules\Shipping\Infrastructure\Models\ZoneRate;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('nace con los seis destinos desactivados y los transportistas de partida, sin tarifas inventadas', function () {
    expect(ZoneRate::count())->toBe(6)
        ->and(ZoneRate::where('active', true)->count())->toBe(0)
        ->and(Carrier::pluck('name')->sort()->values()->all())->toBe(['GLS', 'NACEX'])
        ->and(app(QuoteShipping::class)(ShippingZone::Peninsula, 500, 3000))->toBeNull();
});

it('calcula el envío con el baremo, el envío gratis y el plazo del destino', function () {
    $rate = ZoneRate::where('zone', ShippingZone::Peninsula)->sole();
    $rate->update(['carrier_id' => Carrier::where('name', 'NACEX')->value('id'), 'active' => true, 'free_from_cents' => 6000, 'min_days' => 1, 'max_days' => 2]);
    $rate->bands()->createMany([['max_weight_grams' => 1000, 'price_cents' => 495], ['max_weight_grams' => 3000, 'price_cents' => 650]]);

    $quote = app(QuoteShipping::class);
    expect($quote(ShippingZone::Peninsula, 800, 3990))
        ->priceCents->toBe(495)->free->toBeFalse()->carrier->toBe('NACEX')
        ->and($quote(ShippingZone::Peninsula, 800, 3990)->deliveryLabel())->toBe('Entrega en 1–2 días laborables')
        ->and($quote(ShippingZone::Peninsula, 2500, 3990)->priceCents)->toBe(650)
        ->and($quote(ShippingZone::Peninsula, 2500, 6000))->priceCents->toBe(0)->free->toBeTrue()
        ->and($quote(ShippingZone::Peninsula, 9000, 3990))->toBeNull(); // pasa del último tramo
});

it('los envíos solo los gestiona el superadministrador', function (Role $role, int $status) {
    $this->actingAs(User::factory()->withRole($role)->create());

    $this->get('/admin/tarifas-envio')->assertStatus($status);
    $this->get('/admin/transportistas')->assertStatus($status);
})->with([
    'superadmin' => [Role::Superadmin, 200],
    'gestor de catálogo' => [Role::CatalogManager, 403],
    'fabricante' => [Role::Manufacturer, 403],
]);

it('se configura un destino desde el panel con pesos en kilos', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->withRole(Role::Superadmin)->create());
    $rate = ZoneRate::where('zone', ShippingZone::Balearics)->sole();

    Livewire::test(EditShippingRate::class, ['record' => $rate->getRouteKey()])
        ->fillForm([
            'active' => true,
            'carrier_id' => Carrier::where('name', 'GLS')->value('id'),
            'min_days' => 2,
            'max_days' => 4,
            'free_from_cents' => '',
            'bands' => [['max_weight_grams' => '0,5', 'price_cents' => '6,90'], ['max_weight_grams' => '2', 'price_cents' => '9,90']],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($rate->fresh()->bands->map(fn ($b) => [$b->max_weight_grams, $b->price_cents])->all())->toBe([[500, 690], [2000, 990]])
        ->and(app(QuoteShipping::class)(ShippingZone::Balearics, 1200, 1000)->priceCents)->toBe(990);
});
