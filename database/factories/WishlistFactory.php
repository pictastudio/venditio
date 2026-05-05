<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use PictaStudio\Venditio\Models\{User, Wishlist};

class WishlistFactory extends Factory
{
    protected $model = Wishlist::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'slug' => fn (array $attributes): string => Str::slug($attributes['name']),
            'description' => fake()->optional()->sentence(),
            'is_default' => false,
            'metadata' => ['source' => 'factory'],
        ];
    }
}
