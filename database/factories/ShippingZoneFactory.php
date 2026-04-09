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
            'code' => mb_strtoupper(fake()->unique()->bothify('ZONE-###')),
            'name' => fake()->city(),
            'active' => true,
            'priority' => fake()->numberBetween(0, 10),
        ];
    }
}
