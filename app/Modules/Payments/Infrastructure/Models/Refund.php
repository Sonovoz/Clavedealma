<?php

namespace App\Modules\Payments\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payment_id
 * @property int $order_id
 * @property int $amount_cents
 * @property string $idempotency_key
 * @property ?string $provider_reference
 * @property string $status
 * @property string $reason
 * @property ?string $failure_message
 * @property int $attempts
 * @property Carbon $created_at
 */
class Refund extends Model
{
    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    protected $table = 'payment_refunds';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'attempts' => 'integer'];
    }
}
