<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\Tag;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'active' => true,
            'show_in_menu' => fake()->boolean(),
            'in_evidence' => fake()->boolean(),
            'sort_order' => fake()->unique()->numberBetween(0, 1000),
            'visible_from' => null,
            'visible_until' => null,
        ];
    }
}
