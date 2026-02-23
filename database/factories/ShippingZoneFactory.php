<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\ShippingZone;

class ShippingZoneFactory extends Factory
{
    protected $model = ShippingZone::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->bothify('ZONE###')),
            'name' => fake()->city(),
            'active' => true,
            'priority' => 0,
            'is_fallback' => false,
            'metadata' => null,
        ];
    }
}
