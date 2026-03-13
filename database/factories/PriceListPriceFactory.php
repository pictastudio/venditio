<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\{PriceList, PriceListPrice, Product};

class PriceListPriceFactory extends Factory
{
    protected $model = PriceListPrice::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'price_list_id' => PriceList::factory(),
            'price' => fake()->randomFloat(2, 1, 1_000),
            'purchase_price' => fake()->optional()->randomFloat(2, 1, 500),
            'price_includes_tax' => true,
            'is_default' => false,
            'metadata' => null,
        ];
    }
}
