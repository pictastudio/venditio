<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Brand, Inventory, Product, ProductCategory, ProductType, ProductVariant, ProductVariantOption, TaxClass};

use function Pest\Laravel\{assertDatabaseHas, assertDatabaseMissing, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

it('creates a product with categories', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $category = ProductCategory::factory()->create();

    $payload = [
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Sample Product',
        'sku' => 'SAMPLE-PRODUCT-001',
        'status' => ProductStatus::Published,
        'category_ids' => [$category->getKey()],
    ];

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', $payload)
        ->assertCreated()
        ->assertJsonFragment([
            'name' => 'Sample Product',
            'status' => ProductStatus::Published,
        ]);

    $productId = $response->json('id');

    expect($productId)->not->toBeNull();
    assertDatabaseHas('products', ['id' => $productId]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => app()->getLocale(),
        'attribute' => 'name',
        'value' => 'Sample Product',
    ]);
    assertDatabaseHas('product_category_product', [
        'product_id' => $productId,
        'product_category_id' => $category->getKey(),
    ]);
});

it('validates product creation', function () {
    postJson(config('venditio.routes.api.v1.prefix') . '/products', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'status']);
});

it('creates a product and generates sku when omitted', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Generated Product',
        'status' => ProductStatus::Published,
    ])->assertCreated();

    $productId = $response->json('id');

    assertDatabaseHas('products', [
        'id' => $productId,
        'sku' => config('venditio.product.sku_prefix') . '1',
    ]);
});

it('validates sku uniqueness when provided on product creation', function () {
    Product::factory()->create([
        'sku' => 'DUPLICATE-SKU',
    ]);

    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();

    postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Product with duplicate sku',
        'sku' => 'DUPLICATE-SKU',
        'status' => ProductStatus::Published,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['sku']);
});

it('generates sku with configured prefix and incremental counter', function () {
    config()->set('venditio.product.sku_prefix', 'PRD-');

    Product::factory()->create([
        'sku' => 'PRD-9',
    ]);

    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Prefixed Product',
        'status' => ProductStatus::Published,
    ])->assertCreated();

    assertDatabaseHas('products', [
        'id' => $response->json('id'),
        'sku' => 'PRD-10',
    ]);
});

it('generates sku with configured zero padded counter', function () {
    config()->set('venditio.product.sku_prefix', 'PRD-');
    config()->set('venditio.product.sku_counter_padding', 5);

    Product::factory()->create([
        'sku' => 'PRD-00009',
    ]);

    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Padded Product',
        'status' => ProductStatus::Published,
    ])->assertCreated();

    assertDatabaseHas('products', [
        'id' => $response->json('id'),
        'sku' => 'PRD-00010',
    ]);
});

it('assigns default product type when product_type_id is omitted and a default exists', function () {
    $defaultProductType = ProductType::factory()->create(['is_default' => true]);
    $taxClass = TaxClass::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Product with default type',
        'sku' => 'DEFAULT-TYPE-001',
        'status' => ProductStatus::Published,
    ])->assertCreated();

    $productId = $response->json('id');
    assertDatabaseHas('products', [
        'id' => $productId,
        'product_type_id' => $defaultProductType->getKey(),
    ]);
});

it('assigns default tax class when tax_class_id is omitted and a default exists', function () {
    $defaultTaxClass = TaxClass::factory()->create(['is_default' => true]);
    $productType = ProductType::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'product_type_id' => $productType->getKey(),
        'name' => 'Product with default tax class',
        'sku' => 'DEFAULT-TAX-001',
        'status' => ProductStatus::Published,
    ])->assertCreated();

    $productId = $response->json('id');
    assertDatabaseHas('products', [
        'id' => $productId,
        'tax_class_id' => $defaultTaxClass->getKey(),
    ]);
});

it('returns validation error when tax_class_id is omitted and no default tax class exists', function () {
    ProductType::factory()->create(['is_default' => true]);

    postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'name' => 'Product without tax class',
        'sku' => 'NO-TAX-CLASS-001',
        'status' => ProductStatus::Published,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['tax_class_id']);
});

it('updates product categories when provided', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $category = ProductCategory::factory()->create();
    $otherCategory = ProductCategory::factory()->create();

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $product->categories()->sync([$category->getKey()]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}", [
        'category_ids' => [$otherCategory->getKey()],
    ])->assertOk();

    assertDatabaseMissing('product_category_product', [
        'product_id' => $product->getKey(),
        'product_category_id' => $category->getKey(),
    ]);
    assertDatabaseHas('product_category_product', [
        'product_id' => $product->getKey(),
        'product_category_id' => $otherCategory->getKey(),
    ]);
});

it('creates a product with qty_for_unit', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Product with unit qty',
        'sku' => 'PRODUCT-UNIT-QTY-001',
        'status' => ProductStatus::Published,
        'qty_for_unit' => 6,
    ])->assertCreated()
        ->assertJsonFragment([
            'name' => 'Product with unit qty',
            'qty_for_unit' => 6,
        ]);

    $productId = $response->json('id');
    assertDatabaseHas('products', ['id' => $productId, 'qty_for_unit' => 6]);
});

it('updates a product qty_for_unit', function () {
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'qty_for_unit' => null,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}", [
        'qty_for_unit' => 12,
    ])->assertOk()
        ->assertJsonFragment(['qty_for_unit' => 12]);

    assertDatabaseHas('products', ['id' => $product->getKey(), 'qty_for_unit' => 12]);
});

it('creates a product with nested inventory fields', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Inventory Product',
        'sku' => 'INVENTORY-PRODUCT-001',
        'status' => ProductStatus::Published,
        'inventory' => [
            'stock' => 120,
            'stock_reserved' => 15,
            'stock_min' => 10,
            'price' => 99.50,
            'price_includes_tax' => true,
            'purchase_price' => 65.10,
        ],
    ])->assertCreated();

    $productId = $response->json('id');

    assertDatabaseHas('inventories', [
        'product_id' => $productId,
        'stock' => 120,
        'stock_reserved' => 15,
        'stock_min' => 10,
        'price' => 99.50,
        'price_includes_tax' => true,
        'purchase_price' => 65.10,
        'stock_available' => 105,
    ]);
});

it('updates nested inventory fields via product api', function () {
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    Inventory::factory()->create([
        'product_id' => $product->getKey(),
        'stock' => 10,
        'stock_reserved' => 2,
        'price' => 30,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}", [
        'inventory' => [
            'stock' => 75,
            'stock_reserved' => 5,
            'stock_min' => 8,
            'price' => 120.00,
            'purchase_price' => 70.00,
        ],
    ])->assertOk();

    assertDatabaseHas('inventories', [
        'product_id' => $product->getKey(),
        'stock' => 75,
        'stock_reserved' => 5,
        'stock_min' => 8,
        'price' => 120.00,
        'purchase_price' => 70.00,
        'stock_available' => 70,
    ]);
});

it('excludes variants from products index by default', function () {
    $baseProduct = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $otherBaseProduct = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $variantProduct = Product::factory()->create([
        'parent_id' => $baseProduct->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/products?per_page=100')
        ->assertOk();

    $ids = collect($response->json('data'))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($baseProduct->getKey(), $otherBaseProduct->getKey())
        ->not->toContain($variantProduct->getKey());
});

it('includes variants in products index when configured', function () {
    config()->set('venditio.product.exclude_variants_from_index', false);

    $baseProduct = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $variantProduct = Product::factory()->create([
        'parent_id' => $baseProduct->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/products?per_page=100')
        ->assertOk();

    $ids = collect($response->json('data'))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($baseProduct->getKey(), $variantProduct->getKey());
});

it('includes variants in products index when include_variants is true', function () {
    config()->set('venditio.product.exclude_variants_from_index', true);

    $baseProduct = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $variantProduct = Product::factory()->create([
        'parent_id' => $baseProduct->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/products?per_page=100&include_variants=1')
        ->assertOk();

    $ids = collect($response->json('data'))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($baseProduct->getKey(), $variantProduct->getKey());
});

it('excludes variants in products index when exclude_variants is true', function () {
    config()->set('venditio.product.exclude_variants_from_index', false);

    $baseProduct = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $variantProduct = Product::factory()->create([
        'parent_id' => $baseProduct->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/products?per_page=100&exclude_variants=1')
        ->assertOk();

    $ids = collect($response->json('data'))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($baseProduct->getKey())
        ->not->toContain($variantProduct->getKey());
});

it('prioritizes exclude_variants over include_variants on products index', function () {
    config()->set('venditio.product.exclude_variants_from_index', false);

    $baseProduct = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $variantProduct = Product::factory()->create([
        'parent_id' => $baseProduct->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/products?per_page=100&include_variants=1&exclude_variants=1')
        ->assertOk();

    $ids = collect($response->json('data'))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($baseProduct->getKey())
        ->not->toContain($variantProduct->getKey());
});

it('includes variants and variants options table when requested', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create();

    $size = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
        'name' => 'Size',
        'sort_order' => 10,
    ]);
    $color = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
        'name' => 'Color',
        'sort_order' => 20,
    ]);

    $small = ProductVariantOption::factory()->create([
        'product_variant_id' => $size->getKey(),
        'name' => 's',
        'sort_order' => 10,
    ]);
    $medium = ProductVariantOption::factory()->create([
        'product_variant_id' => $size->getKey(),
        'name' => 'm',
        'sort_order' => 20,
    ]);
    $red = ProductVariantOption::factory()->create([
        'product_variant_id' => $color->getKey(),
        'name' => 'red',
        'sort_order' => 10,
    ]);
    $blue = ProductVariantOption::factory()->create([
        'product_variant_id' => $color->getKey(),
        'name' => 'blue',
        'sort_order' => 20,
    ]);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $variantA = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'parent_id' => $product->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $variantA->variantOptions()->sync([$small->getKey(), $red->getKey()]);

    $variantB = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'parent_id' => $product->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $variantB->variantOptions()->sync([$medium->getKey(), $blue->getKey()]);

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}?include=variants,variants_options_table")
        ->assertOk()
        ->assertJsonCount(2, 'variants')
        ->assertJsonPath('variants_options_table.0.id', $size->getKey())
        ->assertJsonPath('variants_options_table.0.name', 'Size')
        ->assertJsonPath('variants_options_table.0.values.0.value', 's')
        ->assertJsonPath('variants_options_table.0.values.1.value', 'm')
        ->assertJsonPath('variants_options_table.1.id', $color->getKey())
        ->assertJsonPath('variants_options_table.1.name', 'Color')
        ->assertJsonPath('variants_options_table.1.values.0.value', 'red')
        ->assertJsonPath('variants_options_table.1.values.1.value', 'blue');
});

it('rejects unknown includes on products api', function () {
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}?include=unknown")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['include.0']);
});

it('always exposes price_calculated on product payloads', function () {
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $product->inventory()->updateOrCreate([], [
        'price' => 42.50,
        'purchase_price' => 20,
        'price_includes_tax' => false,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}")
        ->assertOk()
        ->assertJsonPath('price_calculated.price', 42.5)
        ->assertJsonPath('price_calculated.purchase_price', 20)
        ->assertJsonPath('price_calculated.price_includes_tax', false);
});
