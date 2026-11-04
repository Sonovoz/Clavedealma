<?php

use App\Modules\Shipping\Contracts\ShippingZone;
use App\Modules\Shipping\Domain\WeightScale;
use App\Modules\Shipping\Domain\ZoneResolver;

it('reconoce el destino por país y código postal', function (string $country, ?string $cp, ?ShippingZone $zone) {
    expect(ZoneResolver::resolve($country, $cp))->toBe($zone);
})->with([
    'Sevilla' => ['ES', '41001', ShippingZone::Peninsula],
    'Palma' => ['es', '07001', ShippingZone::Balearics],
    'Las Palmas' => ['ES', '35001', ShippingZone::Canaries],
    'Tenerife' => ['ES', '38001', ShippingZone::Canaries],
    'Ceuta' => ['ES', '51001', ShippingZone::CeutaMelilla],
    'Melilla' => ['ES', '52001', ShippingZone::CeutaMelilla],
    'Lisboa' => ['PT', '1100-148', ShippingZone::Portugal],
    'París' => ['FR', '75001', ShippingZone::Europe],
    'Estados Unidos' => ['US', '10001', null],
]);

it('aplica el primer tramo del baremo que cubre el peso', function () {
    $bands = [['max_grams' => 5000, 'price_cents' => 890], ['max_grams' => 1000, 'price_cents' => 495], ['max_grams' => 2000, 'price_cents' => 650]];

    expect(WeightScale::price($bands, 300))->toBe(495)
        ->and(WeightScale::price($bands, 1000))->toBe(495)
        ->and(WeightScale::price($bands, 1001))->toBe(650)
        ->and(WeightScale::price($bands, 4800))->toBe(890)
        ->and(WeightScale::price($bands, 5001))->toBeNull()
        ->and(WeightScale::price([], 100))->toBeNull();
});
