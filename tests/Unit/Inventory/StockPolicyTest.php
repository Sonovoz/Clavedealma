<?php

use App\Modules\Inventory\Contracts\StockMode;
use App\Modules\Inventory\Domain\StockPolicy;

it('bajo pedido siempre se puede vender y nada sale de stock', function () {
    expect(StockPolicy::canSell(StockMode::OnDemand, 0, 50))->toBeTrue()
        ->and(StockPolicy::fromStock(StockMode::OnDemand, 10, 3))->toBe(0);
});

it('con unidades limitadas no se vende más de lo que queda', function () {
    expect(StockPolicy::canSell(StockMode::LimitedThenSoldOut, 5, 5))->toBeTrue()
        ->and(StockPolicy::canSell(StockMode::LimitedThenSoldOut, 5, 6))->toBeFalse()
        ->and(StockPolicy::canSell(StockMode::LimitedThenSoldOut, 0, 1))->toBeFalse();
});

it('stock y después bajo pedido: lo que falta se fabrica', function () {
    expect(StockPolicy::canSell(StockMode::LimitedThenOnDemand, 2, 5))->toBeTrue()
        ->and(StockPolicy::fromStock(StockMode::LimitedThenOnDemand, 2, 5))->toBe(2);
});

it('avisa de stock bajo solo si hay umbral y se controlan unidades', function () {
    expect(StockPolicy::isLow(StockMode::LimitedThenSoldOut, 2, 2))->toBeTrue()
        ->and(StockPolicy::isLow(StockMode::LimitedThenSoldOut, 3, 2))->toBeFalse()
        ->and(StockPolicy::isLow(StockMode::LimitedThenSoldOut, 0, null))->toBeFalse()
        ->and(StockPolicy::isLow(StockMode::OnDemand, 0, 5))->toBeFalse();
});

it('no se puede vender cero unidades', function () {
    expect(StockPolicy::canSell(StockMode::OnDemand, 0, 0))->toBeFalse();
});
