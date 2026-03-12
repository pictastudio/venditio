<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\{DiscountType, ProductStatus};
use PictaStudio\Venditio\Models\{Brand, Product, ProductCategory, ProductType, TaxClass};

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

function createScopedDiscountForIncludeTest($model, string $code): void
{
    $model->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'code' => $code,
        'active' => true,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
    ]);
}

it('includes discounts on discountable show endpoints when requested', function () {
    $taxClass = TaxClass::factory()->create();

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
    $productType = ProductType::factory()->create([
        'active' => true,
    ]);

    createScopedDiscountForIncludeTest($product, 'PRODUCT10');
    createScopedDiscountForIncludeTest($brand, 'BRAND10');
    createScopedDiscountForIncludeTest($productCategory, 'CATEGORY10');
    createScopedDiscountForIncludeTest($productType, 'TYPE10');

    $prefix = config('venditio.routes.api.v1.prefix');

    getJson($prefix . '/products/' . $product->getKey() . '?include=discounts')
        ->assertOk()
        ->assertJsonPath('discounts.0.code', 'PRODUCT10');

    getJson($prefix . '/brands/' . $brand->getKey() . '?include=discounts')
        ->assertOk()
        ->assertJsonPath('discounts.0.code', 'BRAND10');

    getJson($prefix . '/product_categories/' . $productCategory->getKey() . '?include=discounts')
        ->assertOk()
        ->assertJsonPath('discounts.0.code', 'CATEGORY10');

    getJson($prefix . '/product_types/' . $productType->getKey() . '?include=discounts')
        ->assertOk()
        ->assertJsonPath('discounts.0.code', 'TYPE10');
});

it('includes discounts on discountable index endpoints when requested', function () {
    $taxClass = TaxClass::factory()->create();

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
    $productType = ProductType::factory()->create([
        'active' => true,
    ]);

    createScopedDiscountForIncludeTest($product, 'PRODUCT-LIST');
    createScopedDiscountForIncludeTest($brand, 'BRAND-LIST');
    createScopedDiscountForIncludeTest($productCategory, 'CATEGORY-LIST');
    createScopedDiscountForIncludeTest($productType, 'TYPE-LIST');

    $prefix = config('venditio.routes.api.v1.prefix');

    getJson($prefix . '/products?all=1&include=discounts&id[]=' . $product->getKey())
        ->assertOk()
        ->assertJsonPath('0.discounts.0.code', 'PRODUCT-LIST');

    getJson($prefix . '/brands?all=1&include=discounts&id[]=' . $brand->getKey())
        ->assertOk()
        ->assertJsonPath('0.discounts.0.code', 'BRAND-LIST');

    getJson($prefix . '/product_categories?all=1&include=discounts&id[]=' . $productCategory->getKey())
        ->assertOk()
        ->assertJsonPath('0.discounts.0.code', 'CATEGORY-LIST');

    getJson($prefix . '/product_types?all=1&include=discounts&id[]=' . $productType->getKey())
        ->assertOk()
        ->assertJsonPath('0.discounts.0.code', 'TYPE-LIST');
});
