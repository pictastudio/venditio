<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\{ShippingRate, ShippingRateTier};

class ShippingRateTierFactory extends Factory
{
    protected $model = ShippingRateTier::class;

    public function definition(): array
    {
        return [
            'shipping_rate_id' => ShippingRate::factory(),
            'from_weight_kg' => 0,
            'to_weight_kg' => null,
            'additional_fee' => fake()->randomFloat(2, 0, 20),
            'sort_order' => 0,
        ];
    }
}
