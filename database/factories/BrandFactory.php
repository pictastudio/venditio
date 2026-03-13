<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\Brand;

class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'active' => true,
            'show_in_menu' => fake()->boolean(),
            'in_evidence' => fake()->boolean(),
            'sort_order' => fake()->unique()->numberBetween(0, 1000),
        ];
    }
}
