<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\ProductCollection;

class ProductCollectionFactory extends Factory
{
    protected $model = ProductCollection::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'active' => true,
            'visible_from' => null,
            'visible_until' => null,
        ];
    }
}
