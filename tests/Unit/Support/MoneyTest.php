<?php

use App\Support\Money;

it('interpreta importes escritos en formato español', function (string $input, int $cents) {
    expect(Money::parse($input)->cents)->toBe($cents);
})->with([
    ['29,90', 2990],
    ['29.90', 2990],
    ['1.234,50', 123450],
    ['29', 2900],
    ['0,05', 5],
    ['24,9', 2490],
]);

it('rechaza importes no válidos', function () {
    Money::parse('29,999');
})->throws(InvalidArgumentException::class);

it('formatea en euros', function () {
    expect(Money::fromCents(123450)->format())->toBe('1.234,50 €')
        ->and(Money::fromCents(5)->format())->toBe('0,05 €')
        ->and(Money::fromCents(-990)->format())->toBe('-9,90 €');
});

it('calcula la base sin IVA redondeando al céntimo', function () {
    expect(Money::fromCents(3630)->withoutVat(2100)->cents)->toBe(3000)
        ->and(Money::fromCents(2990)->withoutVat(2100)->cents)->toBe(2471)
        ->and(Money::fromCents(1000)->withoutVat(1000)->cents)->toBe(909);
});

it('suma y multiplica sin perder céntimos', function () {
    expect(Money::fromCents(2990)->multiply(3)->add(Money::fromCents(10))->cents)->toBe(8980);
});
