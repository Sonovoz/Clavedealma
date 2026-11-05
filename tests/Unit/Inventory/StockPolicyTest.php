<?php

use App\Modules\Inventory\Contracts\StockStatus;
use App\Modules\Inventory\Domain\StockPolicy;

it('con existencias y sin gestionar inventario no hay límite (bajo pedido)', function () {
    expect(StockPolicy::limit(StockStatus::InStock, false, 0))->toBeNull()
        ->and(StockPolicy::taken(false, 0, 5))->toBe(0);
});

it('«Agotado» no se vende, tenga o no cantidad', function () {
    expect(StockPolicy::limit(StockStatus::OutOfStock, false, 0))->toBe(0)
        ->and(StockPolicy::limit(StockStatus::OutOfStock, true, 20))->toBe(0);
});

it('gestionando inventario se vende hasta la cantidad, nunca en negativo', function () {
    expect(StockPolicy::limit(StockStatus::InStock, true, 7))->toBe(7)
        ->and(StockPolicy::limit(StockStatus::InStock, true, -2))->toBe(0)
        ->and(StockPolicy::taken(true, 3, 5))->toBe(3)
        ->and(StockPolicy::taken(true, 10, 4))->toBe(4);
});

it('avisa de pocas existencias solo si se gestiona y hay umbral', function () {
    expect(StockPolicy::isLow(true, 2, 3))->toBeTrue()
        ->and(StockPolicy::isLow(true, 5, 3))->toBeFalse()
        ->and(StockPolicy::isLow(true, 0, null))->toBeFalse()
        ->and(StockPolicy::isLow(false, 0, 3))->toBeFalse();
});
