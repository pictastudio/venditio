<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\ShippingMethod;

class ShippingMethodFactory extends Factory
{
    protected $model = ShippingMethod::class;

    public function definition(): array
    {
        return [
            'code' => mb_strtoupper(fake()->unique()->bothify('SHIP-###')),
            'name' => fake()->company(),
            'active' => true,
            'flat_fee' => fake()->randomFloat(2, 0, 25),
            'volumetric_divisor' => fake()->randomElement([4000, 5000, 6000]),
        ];
    }
}
