<?php

namespace App\Modules\Orders\Infrastructure\Models;

use App\Modules\Orders\Contracts\OrderStatus;
use App\Modules\Shipping\Contracts\ShippingZone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Pedido. Los datos del cliente, las líneas y los importes no se modifican después de
 * crearlo: solo avanza el estado (y queda constancia en OrderEvent).
 *
 * @property int $id
 * @property ?string $reference
 * @property string $token
 * @property OrderStatus $status
 * @property ?string $review_reason
 * @property bool $is_test
 * @property string $email
 * @property string $customer_name
 * @property string $phone
 * @property string $address_line1
 * @property ?string $address_line2
 * @property string $postal_code
 * @property string $city
 * @property ?string $province
 * @property string $country
 * @property ?string $customer_note
 * @property ShippingZone $shipping_zone
 * @property string $shipping_carrier
 * @property int $shipping_cents
 * @property bool $shipping_free
 * @property int $delivery_min_days
 * @property int $delivery_max_days
 * @property int $lead_time_min_days
 * @property int $lead_time_max_days
 * @property int $weight_grams
 * @property int $subtotal_cents
 * @property int $total_cents
 * @property int $vat_cents
 * @property string $currency
 * @property string $terms_version
 * @property Carbon $terms_accepted_at
 * @property ?Carbon $paid_at
 * @property ?Carbon $cancelled_at
 * @property ?Carbon $production_started_at
 * @property ?Carbon $ready_at
 * @property ?Carbon $shipped_at
 * @property ?Carbon $delivered_at
 * @property ?string $tracking_carrier
 * @property ?string $tracking_number
 * @property ?string $tracking_url
 * @property Carbon $created_at
 * @property-read Collection<int, OrderLine> $lines
 * @property-read Collection<int, OrderEvent> $events
 */
class Order extends Model
{
    /** Lo único que puede cambiar después de crear el pedido. */
    private const MUTABLE = [
        'reference', 'status', 'review_reason', 'paid_at', 'cancelled_at', 'updated_at',
        'production_started_at', 'ready_at', 'shipped_at', 'delivered_at', 'tracking_carrier', 'tracking_number', 'tracking_url',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'shipping_zone' => ShippingZone::class,
            'is_test' => 'boolean',
            'shipping_free' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'production_started_at' => 'datetime',
            'ready_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Order $order): void {
            $changed = array_diff(array_keys($order->getDirty()), self::MUTABLE);
            if ($changed !== []) {
                throw new LogicException('Un pedido no se modifica: '.implode(', ', $changed).'. Se corrige con un registro nuevo.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Los pedidos no se borran.'));
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(OrderLine::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    public function log(string $type, ?string $note = null): void
    {
        $this->events()->create(['type' => $type, 'note' => $note === null ? null : mb_substr($note, 0, 500)]);
    }

    public function itemCount(): int
    {
        return (int) $this->lines->sum('quantity');
    }
}
