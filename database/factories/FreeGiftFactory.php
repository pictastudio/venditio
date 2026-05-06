<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Enums\{FreeGiftMode, FreeGiftProductMatchMode, FreeGiftSelectionMode};
use PictaStudio\Venditio\Models\FreeGift;

class FreeGiftFactory extends Factory
{
    protected $model = FreeGift::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'mode' => FreeGiftMode::Automatic,
            'selection_mode' => FreeGiftSelectionMode::Multiple,
            'allow_decline' => false,
            'active' => false,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'minimum_cart_subtotal' => null,
            'maximum_cart_subtotal' => null,
            'minimum_cart_quantity' => null,
            'maximum_cart_quantity' => null,
            'product_match_mode' => FreeGiftProductMatchMode::Any,
        ];
    }
}
