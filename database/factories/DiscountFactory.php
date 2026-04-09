<?php

namespace PictaStudio\Venditio\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use PictaStudio\Venditio\Enums\DiscountType;
use PictaStudio\Venditio\Models\{Discount, Product};

class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    public function definition(): array
    {
        return [
            'discountable_type' => 'product',
            'discountable_id' => Product::factory(),
            'type' => fake()->randomElement(DiscountType::cases())->value,
            'value' => fake()->numberBetween(1, 100),
            'name' => fake()->word(),
            'code' => mb_strtoupper(fake()->bothify('DISC-#####')),
            'active' => true,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'apply_to_cart_total' => false,
            'apply_once_per_cart' => false,
            'max_uses_per_user' => null,
            'one_per_user' => false,
            'free_shipping' => false,
            'first_purchase_only' => false,
            'minimum_order_total' => null,
        ];
    }

    public function percentage(): self
    {
        return $this->state([
            'type' => DiscountType::Percentage,
        ]);
    }

    public function fixed(): self
    {
        return $this->state([
            'type' => DiscountType::Fixed,
        ]);
    }
}
