<?php

namespace App\Modules\Payments\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string $event_id
 * @property string $type
 */
class WebhookEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'payment_webhook_events';

    protected $guarded = ['id'];
}
