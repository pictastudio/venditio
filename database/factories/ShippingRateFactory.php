<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\{ShippingCarrier, ShippingRate, ShippingZone};

class ShippingRateFactory extends Factory
{
    protected $model = ShippingRate::class;

    public function definition(): array
    {
        return [
            'shipping_carrier_id' => ShippingCarrier::factory(),
            'shipping_zone_id' => ShippingZone::factory(),
            'name' => fake()->words(2, true),
            'active' => true,
            'base_fee' => fake()->randomFloat(2, 0, 20),
            'min_order_subtotal' => null,
            'max_order_subtotal' => null,
            'estimated_delivery_min_days' => null,
            'estimated_delivery_max_days' => null,
            'metadata' => null,
        ];
    }
}
