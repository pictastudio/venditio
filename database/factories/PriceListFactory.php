<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\PriceList;

class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->bothify('PL-###??'),
            'active' => true,
            'allow_discounts' => true,
            'description' => fake()->optional()->sentence(),
            'metadata' => null,
        ];
    }
}
