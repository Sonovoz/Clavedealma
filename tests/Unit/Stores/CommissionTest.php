<?php

use App\Modules\Stores\Domain\Commission;

it('convierte porcentajes a puntos básicos', function () {
    expect(Commission::fromPercent('25')->basisPoints)->toBe(2500)
        ->and(Commission::fromPercent('12,5')->basisPoints)->toBe(1250)
        ->and(Commission::fromPercent(7.25)->basisPoints)->toBe(725);
});

it('calcula la comisión en céntimos redondeando los medios hacia arriba', function () {
    expect(Commission::fromBasisPoints(2500)->of(2471))->toBe(618)   // 617,75
        ->and(Commission::fromBasisPoints(1250)->of(100))->toBe(13)  // 12,5
        ->and(Commission::fromBasisPoints(0)->of(5000))->toBe(0);
});

it('no admite comisiones fuera de 0–100 %', function () {
    Commission::fromBasisPoints(10_001);
})->throws(InvalidArgumentException::class);

it('muestra la comisión legible', function () {
    expect(Commission::fromBasisPoints(2500)->label())->toBe('25 %')
        ->and(Commission::fromBasisPoints(1250)->label())->toBe('12,5 %');
});
