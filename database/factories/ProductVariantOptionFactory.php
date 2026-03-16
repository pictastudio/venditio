<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\{ProductVariant, ProductVariantOption};

class ProductVariantOptionFactory extends Factory
{
    protected $model = ProductVariantOption::class;

    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'name' => fake()->randomElement(['option1', 'option2', 'option3']),
            'hex_color' => null,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
