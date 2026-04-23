<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use PictaStudio\Venditio\Enums\{DiscountType, ProductStatus};
use PictaStudio\Venditio\Models\{Discount, Product, ProductCollection};

use function Pest\Laravel\{assertDatabaseHas, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

function discountApiListData(array $json): array
{
    return is_array(data_get($json, 'data'))
        ? data_get($json, 'data')
        : $json;
}

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
        'first_purchase_only' => false,
        'minimum_order_total' => null,
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    getJson($prefix . '/discounts/' . $discount->getKey())
        ->assertOk()
        ->assertJsonPath('id', $discount->getKey())
        ->assertJsonPath('first_purchase_only', false)
        ->assertJsonPath('discountable.id', $product->getKey())
        ->assertJsonPath('discountable.name', 'Test Polymorphic Product');
});

it('serializes a product collection discountable relation using its dedicated resource', function () {
    $collection = ProductCollection::factory()->create([
        'name' => 'Summer Collection',
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    $discount = $collection->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'name' => 'Scoped Collection Discount',
        'code' => 'POLY-COL10',
        'active' => true,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
        'apply_to_cart_total' => false,
        'apply_once_per_cart' => false,
        'max_uses_per_user' => null,
        'one_per_user' => false,
        'free_shipping' => false,
        'first_purchase_only' => false,
        'minimum_order_total' => null,
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    getJson($prefix . '/discounts/' . $discount->getKey())
        ->assertOk()
        ->assertJsonPath('id', $discount->getKey())
        ->assertJsonPath('discountable.id', $collection->getKey())
        ->assertJsonPath('discountable.name', 'Summer Collection');
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
        'first_purchase_only' => true,
    ])->assertCreated();

    $discountId = $response->json('id');
    $code = (string) $response->json('code');

    expect($code)->not->toBe('')
        ->and(str_starts_with($code, 'AUTO-'))->toBeTrue();

    expect($response->json('first_purchase_only'))->toBeTrue();

    assertDatabaseHas('discounts', [
        'id' => $discountId,
        'discountable_type' => 'product',
        'discountable_id' => $product->getKey(),
        'code' => $code,
    ]);
});

it('allows creating discounts scoped to product collections', function () {
    $collection = ProductCollection::factory()->create([
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    $response = postJson($prefix . '/discounts', [
        'discountable_type' => 'product_collection',
        'discountable_id' => $collection->getKey(),
        'type' => DiscountType::Percentage->value,
        'value' => 10,
        'name' => 'Collection Discount',
        'starts_at' => now()->subHour()->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
    ])->assertCreated();

    assertDatabaseHas('discounts', [
        'id' => $response->json('id'),
        'discountable_type' => 'product_collection',
        'discountable_id' => $collection->getKey(),
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

it('sets starts_at to the current date when updating a discount with a null starts_at', function () {
    $currentDate = Date::parse('2026-04-21 12:34:56');
    $discount = Discount::factory()->create([
        'starts_at' => Date::parse('2026-04-18 08:00:00'),
        'ends_at' => Date::parse('2026-04-30 08:00:00'),
    ]);

    Date::setTestNow($currentDate);

    try {
        $prefix = config('venditio.routes.api.v1.prefix');

        patchJson($prefix . '/discounts/' . $discount->getKey(), [
            'starts_at' => null,
        ])->assertOk()
            ->assertJsonPath('id', $discount->getKey())
            ->assertJsonPath('starts_at', $currentDate->toDateTimeString());

        assertDatabaseHas('discounts', [
            'id' => $discount->getKey(),
            'starts_at' => $currentDate->toDateTimeString(),
        ]);
    } finally {
        Date::setTestNow();
    }
});

it('filters discount index by general discounts', function () {
    $generalDiscount = Discount::factory()->create([
        'discountable_type' => null,
        'discountable_id' => null,
        'code' => 'GENERAL10',
        'apply_to_cart_total' => true,
    ]);

    $scopedDiscount = Discount::factory()->create([
        'discountable_type' => 'product',
        'discountable_id' => Product::factory(),
        'code' => 'PRODUCT10',
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    $generalIds = collect(
        discountApiListData(
            getJson($prefix . '/discounts?all=1&is_general=1')
                ->assertOk()
                ->json()
        )
    )->pluck('id')->all();

    expect($generalIds)->toBe([$generalDiscount->getKey()]);

    $scopedIds = collect(
        discountApiListData(
            getJson($prefix . '/discounts?all=1&is_general=0')
                ->assertOk()
                ->json()
        )
    )->pluck('id')->all();

    expect($scopedIds)->toContain($scopedDiscount->getKey())
        ->not->toContain($generalDiscount->getKey());
});

it('filters discount index by discountable_type using exact matching', function () {
    $productDiscount = Discount::factory()->create([
        'discountable_type' => 'product',
        'discountable_id' => Product::factory(),
        'code' => 'PRODUCT-ONLY10',
    ]);

    Discount::factory()->create([
        'discountable_type' => 'product_collection',
        'discountable_id' => ProductCollection::factory(),
        'code' => 'COLLECTION10',
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    $filteredIds = collect(
        discountApiListData(
            getJson($prefix . '/discounts?all=1&discountable_type=product')
                ->assertOk()
                ->json()
        )
    )->pluck('id')->all();

    expect($filteredIds)->toBe([$productDiscount->getKey()]);
});

it('bulk upserts discounts by updating existing discounts and creating new ones', function () {
    $product = Product::factory()->create([
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    $existingDiscount = Discount::factory()->create([
        'discountable_type' => null,
        'discountable_id' => null,
        'type' => DiscountType::Percentage,
        'value' => 10,
        'code' => 'GLOBAL10',
        'name' => 'Global 10',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    $response = postJson($prefix . '/discounts/bulk/upsert', [
        'discounts' => [
            [
                'id' => $existingDiscount->getKey(),
                'value' => 15,
                'active' => false,
            ],
            [
                'discountable_type' => 'product',
                'discountable_id' => $product->getKey(),
                'type' => DiscountType::Fixed->value,
                'value' => 7.5,
                'name' => 'Product Fixed Discount',
                'starts_at' => now()->subHour()->toDateTimeString(),
                'ends_at' => now()->addDay()->toDateTimeString(),
                'apply_once_per_cart' => true,
            ],
        ],
    ])->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment([
            'id' => $existingDiscount->getKey(),
            'value' => 15,
            'active' => false,
            'code' => 'GLOBAL10',
        ])
        ->assertJsonFragment([
            'discountable_type' => 'product',
            'discountable_id' => $product->getKey(),
            'type' => DiscountType::Fixed->value,
            'value' => 7.5,
            'apply_once_per_cart' => true,
        ]);

    $createdDiscountId = collect($response->json())
        ->firstWhere('discountable_id', $product->getKey())['id'];
    $createdCode = collect($response->json())
        ->firstWhere('discountable_id', $product->getKey())['code'];

    expect($createdCode)->not->toBe('')
        ->and(str_starts_with($createdCode, 'AUTO-'))->toBeTrue();

    assertDatabaseHas('discounts', [
        'id' => $existingDiscount->getKey(),
        'value' => 15,
        'active' => false,
        'code' => 'GLOBAL10',
    ]);

    assertDatabaseHas('discounts', [
        'id' => $createdDiscountId,
        'discountable_type' => 'product',
        'discountable_id' => $product->getKey(),
        'type' => DiscountType::Fixed->value,
        'value' => 7.50,
        'code' => $createdCode,
        'apply_once_per_cart' => true,
    ]);
});

it('validates duplicate ids and duplicate codes in bulk discount upserts', function () {
    $firstDiscount = Discount::factory()->create([
        'discountable_type' => null,
        'discountable_id' => null,
        'code' => 'DISC-ONE',
    ]);
    $secondDiscount = Discount::factory()->create([
        'discountable_type' => null,
        'discountable_id' => null,
        'code' => 'DISC-TWO',
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    postJson($prefix . '/discounts/bulk/upsert', [
        'discounts' => [
            [
                'id' => $firstDiscount->getKey(),
                'code' => 'SHARED-CODE',
            ],
            [
                'id' => $firstDiscount->getKey(),
                'code' => 'SHARED-CODE',
            ],
            [
                'id' => $secondDiscount->getKey(),
                'code' => 'SHARED-CODE',
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['discounts.1.id', 'discounts.1.code', 'discounts.2.code']);
});
