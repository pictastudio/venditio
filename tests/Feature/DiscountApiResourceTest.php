<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\{DiscountType, ProductStatus};
use PictaStudio\Venditio\Models\Product;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('serializes the polymorphic discountable relation using its dedicated resource', function () {
    $product = Product::factory()->create([
        'name' => 'Test Polymorphic Product',
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    $discount = $product->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'name' => 'Scoped Product Discount',
        'code' => 'POLY10',
        'active' => true,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
        'apply_to_cart_total' => false,
        'apply_once_per_cart' => false,
        'max_uses_per_user' => null,
        'one_per_user' => false,
        'free_shipping' => false,
        'minimum_order_total' => null,
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    getJson($prefix . '/discounts/' . $discount->getKey())
        ->assertOk()
        ->assertJsonPath('id', $discount->getKey())
        ->assertJsonPath('discountable.id', $product->getKey())
        ->assertJsonPath('discountable.name', 'Test Polymorphic Product');
});
