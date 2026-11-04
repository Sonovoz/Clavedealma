<?php

namespace App\Modules\Returns\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Han llegado las piezas devueltas. Si están bien ($restock), Inventario las repone.
 */
final class ReturnReceived implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<array{variant_id: int, quantity: int}>  $lines
     */
    public function __construct(public readonly int $returnId, public readonly string $reference, public readonly array $lines, public readonly bool $restock) {}
}
