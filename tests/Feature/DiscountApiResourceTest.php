<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\{DiscountType, ProductStatus};
use PictaStudio\Venditio\Models\Product;

use function Pest\Laravel\{assertDatabaseHas, getJson, postJson};

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

it('generates a discount code automatically when creating a discount scoped to a discountable resource', function () {
    $product = Product::factory()->create([
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    $response = postJson($prefix . '/discounts', [
        'discountable_type' => 'product',
        'discountable_id' => $product->getKey(),
        'type' => DiscountType::Percentage->value,
        'value' => 10,
        'name' => 'Auto Code Discount',
        'starts_at' => now()->subHour()->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
    ])->assertCreated();

    $discountId = $response->json('id');
    $code = (string) $response->json('code');

    expect($code)->not->toBe('')
        ->and(str_starts_with($code, 'AUTO-'))->toBeTrue();

    assertDatabaseHas('discounts', [
        'id' => $discountId,
        'discountable_type' => 'product',
        'discountable_id' => $product->getKey(),
        'code' => $code,
    ]);
});

it('requires code when creating a discount not scoped to a discountable resource', function () {
    $prefix = config('venditio.routes.api.v1.prefix');

    postJson($prefix . '/discounts', [
        'type' => DiscountType::Percentage->value,
        'value' => 10,
        'name' => 'Global Discount Without Code',
        'starts_at' => now()->subHour()->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
});
