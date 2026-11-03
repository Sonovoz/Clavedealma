<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Contracts\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 *
 * `store_id` debe indicarse al usar la factoría (la tienda es de otro módulo).
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->paragraph(),
            'price_cents' => fake()->randomElement([2490, 2990, 3490, 4990]),
            'vat_rate_bps' => 2100,
            'lead_time_min_days' => 5,
            'lead_time_max_days' => 7,
            'status' => ProductStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => ProductStatus::Published]);
    }
}
