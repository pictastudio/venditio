<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PictaStudio\Venditio\Enums\{CartStatus, DiscountType, OrderStatus, ProductStatus};
use PictaStudio\Venditio\Models\{Brand, Cart, CartLine, Currency, Order, OrderLine, Product, ProductCategory, ProductCollection, ProductType, Tag, TaxClass};

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

function createScopedDiscountForIncludeTest($model, string $code, array $overrides = [])
{
    return $model->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'code' => $code,
        'active' => true,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
        ...$overrides,
    ]);
}

function createDiscountableIncludeTargets(): array
{
    $taxClass = TaxClass::factory()->create();
    $currency = Currency::factory()->create([
        'is_enabled' => true,
        'is_default' => true,
    ]);

    $product = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);
    $brand = Brand::factory()->create();
    $productCategory = ProductCategory::factory()->create([
        'active' => true,
        'sort_order' => 1,
    ]);
    $productCollection = ProductCollection::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $productType = ProductType::factory()->create([
        'active' => true,
    ]);
    $tag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $cart = Cart::factory()->create([
        'status' => CartStatus::Active,
        'addresses' => [
            'billing' => [],
            'shipping' => [],
        ],
    ]);
    $cartLine = CartLine::query()->create([
        'cart_id' => $cart->getKey(),
        'product_id' => $product->getKey(),
        'currency_id' => $currency->getKey(),
        'product_name' => 'Discount test product',
        'product_sku' => 'DISCOUNT-TEST',
        'unit_price' => 100,
        'unit_final_price' => 100,
        'unit_final_price_tax' => 0,
        'unit_final_price_taxable' => 100,
        'qty' => 1,
        'total_final_price' => 100,
        'tax_rate' => 0,
        'product_data' => [],
    ]);
    $order = Order::factory()->create([
        'status' => OrderStatus::Processing,
        'addresses' => [
            'billing' => [],
            'shipping' => [],
        ],
    ]);
    $orderLine = OrderLine::query()->create([
        'order_id' => $order->getKey(),
        'product_id' => $product->getKey(),
        'currency_id' => $currency->getKey(),
        'product_name' => 'Discount test product',
        'product_sku' => 'DISCOUNT-TEST',
        'unit_price' => 100,
        'unit_final_price' => 100,
        'unit_final_price_tax' => 0,
        'unit_final_price_taxable' => 100,
        'qty' => 1,
        'total_final_price' => 100,
        'tax_rate' => 0,
        'product_data' => [],
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    return [
        'PRD' => [
            'model' => $product,
            'show' => $prefix . '/products/' . $product->getKey(),
            'index' => $prefix . '/products?all=1&id[]=' . $product->getKey(),
        ],
        'BRD' => [
            'model' => $brand,
            'show' => $prefix . '/brands/' . $brand->getKey(),
            'index' => $prefix . '/brands?all=1&id[]=' . $brand->getKey(),
        ],
        'CAT' => [
            'model' => $productCategory,
            'show' => $prefix . '/product_categories/' . $productCategory->getKey(),
            'index' => $prefix . '/product_categories?all=1&id[]=' . $productCategory->getKey(),
        ],
        'COL' => [
            'model' => $productCollection,
            'show' => $prefix . '/product_collections/' . $productCollection->getKey(),
            'index' => $prefix . '/product_collections?all=1&id[]=' . $productCollection->getKey(),
        ],
        'TYP' => [
            'model' => $productType,
            'show' => $prefix . '/product_types/' . $productType->getKey(),
            'index' => $prefix . '/product_types?all=1&id[]=' . $productType->getKey(),
        ],
        'TAG' => [
            'model' => $tag,
            'show' => $prefix . '/tags/' . $tag->getKey(),
            'index' => $prefix . '/tags?all=1&id[]=' . $tag->getKey(),
        ],
        'CRT' => [
            'model' => $cart,
            'show' => $prefix . '/carts/' . $cart->getKey(),
            'index' => $prefix . '/carts?all=1&id[]=' . $cart->getKey(),
        ],
        'CLN' => [
            'model' => $cartLine,
            'show' => $prefix . '/cart_lines/' . $cartLine->getKey(),
            'index' => $prefix . '/cart_lines?all=1&id[]=' . $cartLine->getKey(),
        ],
        'ORD' => [
            'model' => $order,
            'show' => $prefix . '/orders/' . $order->getKey(),
            'index' => $prefix . '/orders?all=1&id[]=' . $order->getKey(),
        ],
        'OLN' => [
            'model' => $orderLine,
            'show' => $prefix . '/order_lines/' . $orderLine->getKey(),
            'index' => $prefix . '/order_lines?all=1&id[]=' . $orderLine->getKey(),
        ],
    ];
}

function createDiscountIncludeFixtures($model, string $prefix): void
{
    createScopedDiscountForIncludeTest($model, $prefix . '-VALID');
    createScopedDiscountForIncludeTest($model, $prefix . '-EXPIRED', [
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDay(),
    ]);
    createScopedDiscountForIncludeTest($model, $prefix . '-INACTIVE', [
        'active' => false,
    ]);
    createScopedDiscountForIncludeTest($model, $prefix . '-FUTURE', [
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDays(2),
    ]);

    createScopedDiscountForIncludeTest($model, $prefix . '-DELETED')->delete();
}

function assertSeparatedDiscountIncludes(TestResponse $response, string $prefix, ?string $root = null): void
{
    $payload = $response->json();
    $path = fn (string $key): string => filled($root) ? $root . '.' . $key : $key;

    expect(collect(data_get($payload, $path('discounts')))->pluck('code')->all())
        ->toContain($prefix . '-VALID')
        ->toContain($prefix . '-EXPIRED')
        ->toContain($prefix . '-INACTIVE')
        ->toContain($prefix . '-FUTURE')
        ->not->toContain($prefix . '-DELETED');

    expect(collect(data_get($payload, $path('valid_discounts')))->pluck('code')->all())
        ->toBe([$prefix . '-VALID']);

    expect(collect(data_get($payload, $path('expired_discounts')))->pluck('code')->all())
        ->toBe([$prefix . '-EXPIRED']);
}

it('includes all and separated discounts on discountable show endpoints when requested', function () {
    $targets = createDiscountableIncludeTargets();

    foreach ($targets as $prefix => $target) {
        createDiscountIncludeFixtures($target['model'], $prefix);

        $response = getJson($target['show'] . '?include=discounts,valid_discounts,expired_discounts')
            ->assertOk();

        assertSeparatedDiscountIncludes($response, $prefix);
    }
});

it('includes all and separated discounts on discountable index endpoints when requested', function () {
    $targets = createDiscountableIncludeTargets();

    foreach ($targets as $prefix => $target) {
        createDiscountIncludeFixtures($target['model'], $prefix);

        $separator = str_contains($target['index'], '?') ? '&' : '?';
        $response = getJson($target['index'] . $separator . 'include=discounts,valid_discounts,expired_discounts')
            ->assertOk();

        assertSeparatedDiscountIncludes($response, $prefix, '0');
    }
});

it('rejects unsupported discountable include names', function () {
    getJson(config('venditio.routes.api.v1.prefix') . '/tags?include=discount_history')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['include.0']);
});
