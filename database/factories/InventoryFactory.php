<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Models\Inventory;

class InventoryFactory extends Factory
{
    protected $model = Inventory::class;

    public function definition(): array
    {
        return [
            'stock' => fake()->numberBetween(1, 1000),
            'stock_min' => fake()->numberBetween(1, 1000),
            'minimum_reorder_quantity' => fake()->optional()->numberBetween(1, 500),
            'reorder_lead_days' => fake()->optional()->numberBetween(1, 90),
            'manage_stock' => true,
            'price' => fake()->randomFloat(2, 1, 1000),
            'price_includes_tax' => true,
            'purchase_price' => fake()->optional()->randomFloat(2, 1, 1000),
        ];
    }
}
