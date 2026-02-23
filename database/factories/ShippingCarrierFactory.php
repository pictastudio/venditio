<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\ShippingCarrier;

class ShippingCarrierFactory extends Factory
{
    protected $model = ShippingCarrier::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->bothify('CAR###')),
            'name' => fake()->company(),
            'active' => true,
            'volumetric_divisor' => 5000,
            'weight_rounding_step_kg' => 0.5,
            'weight_rounding_mode' => 'ceil',
            'metadata' => null,
        ];
    }
}
