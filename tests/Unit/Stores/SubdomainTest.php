<?php

use App\Modules\Stores\Domain\Subdomain;

it('acepta subdominios válidos', function (string $value) {
    expect(Subdomain::isValid($value))->toBeTrue();
})->with(['lunabruma', 'los-arcos', 'dj99', 'abc']);

it('rechaza subdominios mal formados o reservados', function (string $value) {
    expect(Subdomain::isValid($value))->toBeFalse();
})->with(['ab', '-luna', 'luna-', 'lu--na', 'Luna Bruma', 'luna_bruma', 'admin', 'www', 'checkout', str_repeat('a', 41)]);

it('normaliza a minúsculas y construye el host', function () {
    expect((new Subdomain('  LunaBruma '))->host('clavedealma.es'))->toBe('lunabruma.clavedealma.es');
});
