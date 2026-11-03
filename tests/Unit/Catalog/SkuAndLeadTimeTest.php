<?php

use App\Modules\Catalog\Domain\LeadTime;
use App\Modules\Catalog\Domain\Sku;

it('propone una referencia legible', function () {
    expect(Sku::suggest('lunabruma', 'camiseta-gira', 'XL', 'Azul marino'))->toBe('LUNABRUMA-CAMISETA-GIRA-XL-AZUL-MARINO')
        ->and(Sku::suggest('losarcos', 'gorra', null, 'Negro'))->toBe('LOSARCOS-GORRA-NEGRO');
});

it('describe el plazo de fabricación', function () {
    expect((new LeadTime(5, 7))->label())->toBe('Fabricado en 5–7 días')
        ->and((new LeadTime(3, 3))->label())->toBe('Fabricado en 3 días');
});

it('rechaza plazos imposibles', function () {
    new LeadTime(7, 5);
})->throws(InvalidArgumentException::class);
