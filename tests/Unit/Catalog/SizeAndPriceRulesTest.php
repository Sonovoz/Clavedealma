<?php

use App\Modules\Catalog\Domain\ReferencePrice;
use App\Modules\Catalog\Domain\SizeScale;

it('unifica la escritura de las tallas y las ordena según la escala', function () {
    expect(SizeScale::normalize(' 2xl '))->toBe('XXL')
        ->and(SizeScale::normalize('xxxl'))->toBe('3XL')
        ->and(SizeScale::normalize('Talla única'))->toBeNull()
        ->and(SizeScale::normalize(''))->toBeNull()
        ->and(SizeScale::normalize('42'))->toBe('42')
        ->and(SizeScale::sort(['XL', '3XL', 'S', '42', 'XS', '2XL']))->toBe(['XS', 'S', 'XL', '2XL', '3XL', '42']);
});

$dia = 86_400;

it('toma como precio anterior el más bajo de los 30 días previos a la rebaja', function () use ($dia) {
    $hoy = 100 * $dia;

    expect(ReferencePrice::discount([
        ['cents' => 4995, 'since' => $hoy - 60 * $dia],
        ['cents' => 4790, 'since' => $hoy - 20 * $dia], // bajó dentro de los 30 días
        ['cents' => 4995, 'since' => $hoy - 10 * $dia],
        ['cents' => 4499, 'since' => $hoy],
    ]))->toBe(['cents' => 4790, 'percent' => 6]);
});

it('cuenta un precio antiguo que seguía vigente al empezar los 30 días', function () use ($dia) {
    expect(ReferencePrice::discount([
        ['cents' => 4995, 'since' => 0],
        ['cents' => 4499, 'since' => 200 * $dia],
    ]))->toBe(['cents' => 4995, 'percent' => 10]);
});

it('no hay rebaja si el precio sube, si es el primero o si el más bajo es el actual', function () use ($dia) {
    expect(ReferencePrice::discount([]))->toBeNull()
        ->and(ReferencePrice::discount([['cents' => 3990, 'since' => 0]]))->toBeNull()
        ->and(ReferencePrice::discount([['cents' => 3990, 'since' => 0], ['cents' => 4490, 'since' => 5 * $dia]]))->toBeNull()
        ->and(ReferencePrice::discount([
            ['cents' => 3490, 'since' => 0],
            ['cents' => 4990, 'since' => 40 * $dia],
            ['cents' => 3490, 'since' => 45 * $dia], // vuelve al precio de siempre: no es rebaja
        ]))->toBeNull();
});
