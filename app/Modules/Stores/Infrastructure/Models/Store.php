<?php

namespace App\Modules\Stores\Infrastructure\Models;

use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Database\Factories\StoreFactory;
use App\Modules\Stores\Domain\Commission;
use App\Modules\Stores\Domain\Subdomain;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $artist_id
 * @property string $name
 * @property string $subdomain
 * @property StoreStatus $status
 * @property int $commission_bps
 * @property ?array<string, mixed> $theme
 * @property string $design
 * @property ?string $tagline
 * @property ?string $bio
 * @property ?Carbon $published_at
 */
#[UseFactory(StoreFactory::class)]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'draft',
        'design' => 'base',
    ];

    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'commission_bps' => 'integer',
            'theme' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Store $store): void {
            $store->subdomain = (new Subdomain($store->subdomain))->value;
            Commission::fromBasisPoints($store->commission_bps); // valida el rango

            if ($store->status === StoreStatus::Published && $store->published_at === null) {
                $store->published_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<Artist, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function commission(): Commission
    {
        return Commission::fromBasisPoints($this->commission_bps);
    }

    public function url(): string
    {
        $scheme = str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http';

        return $scheme.'://'.(new Subdomain($this->subdomain))->host((string) config('clavedealma.domain'));
    }
}
