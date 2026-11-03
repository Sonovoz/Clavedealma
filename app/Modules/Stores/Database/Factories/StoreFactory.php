<?php

namespace App\Modules\Stores\Database\Factories;

use App\Modules\Stores\Contracts\StoreStatus;
use App\Modules\Stores\Infrastructure\Models\Artist;
use App\Modules\Stores\Infrastructure\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'artist_id' => Artist::factory(),
            'name' => fake()->unique()->company(),
            'subdomain' => fake()->unique()->bothify('tienda-####'),
            'status' => StoreStatus::Draft,
            'commission_bps' => 2500,
            'tagline' => fake()->sentence(6),
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => StoreStatus::Published]);
    }
}
