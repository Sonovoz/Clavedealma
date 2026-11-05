<?php

namespace App\Modules\Payments\Infrastructure\Models;

use App\Modules\Payments\Contracts\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string $provider
 * @property ?string $method
 * @property ?string $provider_reference
 * @property int $amount_cents
 * @property string $currency
 * @property PaymentStatus $status
 * @property ?string $failure_message
 * @property ?Carbon $succeeded_at
 * @property Carbon $created_at
 */
class Payment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'succeeded_at' => 'datetime', 'amount_cents' => 'integer'];
    }
}
