<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\{DiscountType, ProductStatus};
use PictaStudio\Venditio\Models\{Brand, Country, CountryTaxClass, Currency, Inventory, PriceList, PriceListPrice, Product, ProductCategory, ProductCollection, ProductType, ProductVariant, ProductVariantOption, Tag, TaxClass};

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

it('creates a product with collections', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $collection = ProductCollection::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Collection Product',
        'sku' => 'COLLECTION-PRODUCT-001',
        'status' => ProductStatus::Published,
        'collection_ids' => [$collection->getKey()],
    ])->assertCreated();

    assertDatabaseHas('product_collection_product', [
        'product_id' => $response->json('id'),
        'product_collection_id' => $collection->getKey(),
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
    $productType = ProductType::factory()->create(['active' => true]);

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

it('updates product collections when provided', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $collection = ProductCollection::factory()->create();
    $otherCollection = ProductCollection::factory()->create();

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $product->collections()->sync([$collection->getKey()]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}", [
        'collection_ids' => [$otherCollection->getKey()],
    ])->assertOk();

    assertDatabaseMissing('product_collection_product', [
        'product_id' => $product->getKey(),
        'product_collection_id' => $collection->getKey(),
    ]);
    assertDatabaseHas('product_collection_product', [
        'product_id' => $product->getKey(),
        'product_collection_id' => $otherCollection->getKey(),
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
            'minimum_reorder_quantity' => 24,
            'reorder_lead_days' => 7,
            'price' => 99.50,
            'price_includes_tax' => true,
            'purchase_price' => 65.10,
        ],
    ])->assertCreated()
        ->assertJsonPath('inventory.minimum_reorder_quantity', 24)
        ->assertJsonPath('inventory.reorder_lead_days', 7);

    $productId = $response->json('id');

    assertDatabaseHas('inventories', [
        'product_id' => $productId,
        'stock' => 120,
        'stock_reserved' => 15,
        'stock_min' => 10,
        'minimum_reorder_quantity' => 24,
        'reorder_lead_days' => 7,
        'manage_stock' => true,
        'price' => 99.50,
        'price_includes_tax' => true,
        'purchase_price' => 65.10,
        'stock_available' => 105,
    ]);
});

it('defaults nested inventory price to zero when omitted on product creation', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Inventory Product Without Price',
        'sku' => 'INVENTORY-PRODUCT-NO-PRICE-001',
        'status' => ProductStatus::Published,
        'inventory' => [
            'stock' => 12,
            'stock_reserved' => 2,
            'stock_min' => 1,
            'minimum_reorder_quantity' => null,
            'reorder_lead_days' => null,
        ],
    ])->assertCreated();

    $productId = $response->json('id');

    assertDatabaseHas('inventories', [
        'product_id' => $productId,
        'stock' => 12,
        'stock_reserved' => 2,
        'stock_min' => 1,
        'manage_stock' => true,
        'price' => 0,
        'stock_available' => 10,
    ]);
});

it('creates a product with stock management disabled in nested inventory', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Inventory Product Without Stock Management',
        'sku' => 'INVENTORY-PRODUCT-NO-STOCK-MANAGEMENT-001',
        'status' => ProductStatus::Published,
        'inventory' => [
            'stock' => 12,
            'manage_stock' => false,
            'price' => 10,
        ],
    ])->assertCreated();

    $productId = $response->json('id');

    assertDatabaseHas('inventories', [
        'product_id' => $productId,
        'stock' => 12,
        'manage_stock' => false,
        'stock_available' => 12,
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
            'minimum_reorder_quantity' => 20,
            'reorder_lead_days' => 9,
            'price' => 120.00,
            'purchase_price' => 70.00,
        ],
    ])->assertOk()
        ->assertJsonPath('inventory.minimum_reorder_quantity', 20)
        ->assertJsonPath('inventory.reorder_lead_days', 9);

    assertDatabaseHas('inventories', [
        'product_id' => $product->getKey(),
        'stock' => 75,
        'stock_reserved' => 5,
        'stock_min' => 8,
        'minimum_reorder_quantity' => 20,
        'reorder_lead_days' => 9,
        'manage_stock' => true,
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

it('filters products index by multiple brands, categories, and collections', function () {
    $brandA = Brand::factory()->create();
    $brandB = Brand::factory()->create();
    $brandC = Brand::factory()->create();

    $categoryA = ProductCategory::factory()->create();
    $categoryB = ProductCategory::factory()->create();
    $categoryC = ProductCategory::factory()->create();

    $collectionA = ProductCollection::factory()->create();
    $collectionB = ProductCollection::factory()->create();
    $collectionC = ProductCollection::factory()->create();

    $matchingA = Product::factory()->create([
        'brand_id' => $brandA->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $matchingA->categories()->sync([$categoryA->getKey()]);
    $matchingA->collections()->sync([$collectionA->getKey()]);

    $matchingB = Product::factory()->create([
        'brand_id' => $brandB->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $matchingB->categories()->sync([$categoryB->getKey()]);
    $matchingB->collections()->sync([$collectionB->getKey()]);

    $notMatchingBrand = Product::factory()->create([
        'brand_id' => $brandC->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $notMatchingBrand->categories()->sync([$categoryA->getKey()]);
    $notMatchingBrand->collections()->sync([$collectionA->getKey()]);

    $notMatchingCategory = Product::factory()->create([
        'brand_id' => $brandA->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $notMatchingCategory->categories()->sync([$categoryC->getKey()]);
    $notMatchingCategory->collections()->sync([$collectionA->getKey()]);

    $notMatchingCollection = Product::factory()->create([
        'brand_id' => $brandA->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $notMatchingCollection->categories()->sync([$categoryA->getKey()]);
    $notMatchingCollection->collections()->sync([$collectionC->getKey()]);

    $response = getJson(
        config('venditio.routes.api.v1.prefix')
        . '/products?all=1'
        . '&brand_ids[]=' . $brandA->getKey()
        . '&brand_ids[]=' . $brandB->getKey()
        . '&category_ids[]=' . $categoryA->getKey()
        . '&category_ids[]=' . $categoryB->getKey()
        . '&collection_ids[]=' . $collectionA->getKey()
        . '&collection_ids[]=' . $collectionB->getKey()
    )->assertOk();

    $json = $response->json();
    $items = is_array(data_get($json, 'data'))
        ? data_get($json, 'data')
        : $json;

    $ids = collect($items)
        ->pluck('id')
        ->all();

    expect($ids)
        ->toContain($matchingA->getKey(), $matchingB->getKey())
        ->not->toContain($notMatchingBrand->getKey(), $notMatchingCategory->getKey(), $notMatchingCollection->getKey());
});

it('validates products index brand_ids, category_ids, and collection_ids filters', function () {
    getJson(
        config('venditio.routes.api.v1.prefix')
        . '/products?brand_ids[]=999999&category_ids[]=999999&collection_ids[]=999999'
    )->assertUnprocessable()
        ->assertJsonValidationErrors(['brand_ids.0', 'category_ids.0', 'collection_ids.0']);
});

it('filters products index by inventory price with operator', function () {
    $productLow = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $productMid = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $productHigh = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $productLow->inventory()->update(['price' => 10]);
    $productMid->inventory()->update(['price' => 20]);
    $productHigh->inventory()->update(['price' => 30]);

    $response = getJson(
        config('venditio.routes.api.v1.prefix')
        . '/products?all=1&price_operator=' . urlencode('>=') . '&price=20'
    )->assertOk();

    $json = $response->json();
    $items = is_array(data_get($json, 'data'))
        ? data_get($json, 'data')
        : $json;

    $ids = collect($items)
        ->pluck('id')
        ->all();

    expect($ids)
        ->toContain($productMid->getKey(), $productHigh->getKey())
        ->not->toContain($productLow->getKey());
});

it('sorts products index by inventory price', function () {
    $productMid = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $productHigh = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $productLow = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $productMid->inventory()->update(['price' => 20]);
    $productHigh->inventory()->update(['price' => 30]);
    $productLow->inventory()->update(['price' => 10]);

    $response = getJson(
        config('venditio.routes.api.v1.prefix') . '/products?all=1&sort_by=price&sort_dir=asc'
    )->assertOk();

    $json = $response->json();
    $items = is_array(data_get($json, 'data'))
        ? data_get($json, 'data')
        : $json;

    $ids = collect($items)
        ->pluck('id')
        ->values()
        ->all();

    expect($ids)->toBe([
        $productLow->getKey(),
        $productMid->getKey(),
        $productHigh->getKey(),
    ]);
});

it('validates inventory price operator on products index', function () {
    getJson(
        config('venditio.routes.api.v1.prefix')
        . '/products?price=10&price_operator=invalid'
    )->assertUnprocessable()
        ->assertJsonValidationErrors(['price_operator']);
});

it('includes variants and variants options table when requested', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create(['active' => true]);

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
    $variantA->forceFill([
        'images' => [[
            'id' => 'shared-red-image',
            'alt' => 'Shared red image',
            'mimetype' => 'image/jpeg',
            'sort_order' => 0,
            'active' => true,
            'thumbnail' => true,
            'shared_from_variant_option' => true,
            'src' => "products/{$product->getKey()}/variant_options/{$red->getKey()}/images/red-shared.jpg",
        ]],
    ])->save();

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
        ->assertJsonMissingPath('variants_relation')
        ->assertJsonPath('variants_options_table.0.id', $size->getKey())
        ->assertJsonPath('variants_options_table.0.name', 'Size')
        ->assertJsonPath('variants_options_table.0.values.0.value', 's')
        ->assertJsonPath('variants_options_table.0.values.1.value', 'm')
        ->assertJsonPath('variants_options_table.1.id', $color->getKey())
        ->assertJsonPath('variants_options_table.1.name', 'Color')
        ->assertJsonPath('variants_options_table.1.values.0.value', 'red')
        ->assertJsonPath('variants_options_table.1.values.0.images.0.alt', 'Shared red image')
        ->assertJsonPath('variants_options_table.1.values.1.value', 'blue')
        ->assertJsonCount(0, 'variants_options_table.1.values.1.images');
});

it('scopes shared variant option images to the requested product variants options table', function () {
    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create(['active' => true]);

    $color = ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
        'name' => 'Color',
    ]);

    $porpora = ProductVariantOption::factory()->create([
        'product_variant_id' => $color->getKey(),
        'name' => 'porpora',
    ]);

    $firstProduct = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $secondProduct = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $firstPorporaVariant = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'parent_id' => $firstProduct->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $firstPorporaVariant->variantOptions()->sync([$porpora->getKey()]);
    $firstPorporaVariant->forceFill([
        'images' => [[
            'id' => 'first-product-porpora-image',
            'alt' => 'First product porpora',
            'mimetype' => 'image/jpeg',
            'sort_order' => 0,
            'active' => true,
            'thumbnail' => true,
            'shared_from_variant_option' => true,
            'src' => "products/{$firstProduct->getKey()}/variant_options/{$porpora->getKey()}/images/porpora.jpg",
        ]],
    ])->save();

    $secondPorporaVariant = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'parent_id' => $secondProduct->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $secondPorporaVariant->variantOptions()->sync([$porpora->getKey()]);

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$firstProduct->getKey()}?include=variants,variants_options_table")
        ->assertOk()
        ->assertJsonPath('variants_options_table.0.values.0.value', 'porpora')
        ->assertJsonPath('variants_options_table.0.values.0.images.0.alt', 'First product porpora');

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$secondProduct->getKey()}?include=variants,variants_options_table")
        ->assertOk()
        ->assertJsonPath('variants_options_table.0.values.0.value', 'porpora')
        ->assertJsonCount(0, 'variants_options_table.0.values.0.images');
});

it('includes variants inside parent when showing a variant with variants requested', function () {
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $variantA = Product::factory()->create([
        'parent_id' => $product->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $variantB = Product::factory()->create([
        'parent_id' => $product->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = getJson(config('venditio.routes.api.v1.prefix') . "/products/{$variantA->getKey()}?include=variants")
        ->assertOk()
        ->assertJsonMissingPath('variants')
        ->assertJsonPath('parent.id', $product->getKey())
        ->assertJsonCount(2, 'parent.variants');

    expect(collect($response->json('parent.variants'))->pluck('id')->all())
        ->toContain($variantA->getKey(), $variantB->getKey());
});

it('includes requested product relations on show endpoint', function () {
    config()->set('venditio.price_lists.enabled', true);

    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create(['active' => true]);
    $category = ProductCategory::factory()->create();
    $collection = ProductCollection::factory()->create();
    $priceList = PriceList::factory()->create(['name' => 'Retail']);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $product->categories()->sync([$category->getKey()]);
    $product->collections()->sync([$collection->getKey()]);

    PriceListPrice::factory()->create([
        'product_id' => $product->getKey(),
        'price_list_id' => $priceList->getKey(),
        'price' => 49.90,
        'is_default' => true,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}?include=brand,categories,collections,product_type,tax_class,price_lists")
        ->assertOk()
        ->assertJsonPath('brand.id', $brand->getKey())
        ->assertJsonPath('categories.0.id', $category->getKey())
        ->assertJsonPath('collections.0.id', $collection->getKey())
        ->assertJsonPath('product_type.id', $productType->getKey())
        ->assertJsonPath('tax_class.id', $taxClass->getKey())
        ->assertJsonPath('price_lists.0.price_list.id', $priceList->getKey());
});

it('includes requested product relations on index endpoint', function () {
    config()->set('venditio.price_lists.enabled', true);

    $brand = Brand::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $productType = ProductType::factory()->create(['active' => true]);
    $category = ProductCategory::factory()->create();
    $collection = ProductCollection::factory()->create();
    $priceList = PriceList::factory()->create(['name' => 'Retail']);

    $product = Product::factory()->create([
        'brand_id' => $brand->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $product->categories()->sync([$category->getKey()]);
    $product->collections()->sync([$collection->getKey()]);

    PriceListPrice::factory()->create([
        'product_id' => $product->getKey(),
        'price_list_id' => $priceList->getKey(),
        'price' => 49.90,
        'is_default' => true,
    ]);

    $response = getJson(
        config('venditio.routes.api.v1.prefix')
        . '/products?all=1&id[]=' . $product->getKey()
        . '&include=brand,categories,collections,product_type,tax_class,price_lists'
    )->assertOk();

    $json = $response->json();
    $items = is_array(data_get($json, 'data'))
        ? data_get($json, 'data')
        : $json;

    $item = collect($items)
        ->first(fn (array $productPayload): bool => (int) ($productPayload['id'] ?? 0) === (int) $product->getKey());

    expect($item)->not->toBeNull()
        ->and(data_get($item, 'brand.id'))->toBe($brand->getKey())
        ->and(data_get($item, 'categories.0.id'))->toBe($category->getKey())
        ->and(data_get($item, 'collections.0.id'))->toBe($collection->getKey())
        ->and(data_get($item, 'product_type.id'))->toBe($productType->getKey())
        ->and(data_get($item, 'tax_class.id'))->toBe($taxClass->getKey())
        ->and(data_get($item, 'price_lists.0.price_list.id'))->toBe($priceList->getKey());
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
        ->assertJsonPath('price_calculated.price_final', 42.5)
        ->assertJsonPath('price_calculated.purchase_price', 20)
        ->assertJsonPath('price_calculated.price_includes_tax', false)
        ->assertJsonMissingPath('price_calculated.price_source')
        ->assertJsonMissingPath('price_calculated.discounts_applied');
});

// it('uses the country-iso-2 header when calculating product tax', function () {
//     $taxClass = TaxClass::factory()->create();
//     $currencyId = Currency::query()->firstOrCreate(
//         ['code' => 'EUR'],
//         ['name' => 'EUR', 'exchange_rate' => 1, 'is_enabled' => true, 'is_default' => false]
//     )->getKey();

//     $italy = Country::query()->create([
//         'name' => 'Italy',
//         'iso_2' => 'IT',
//         'iso_3' => 'ITA',
//         'phone_code' => '+39',
//         'currency_id' => $currencyId,
//         'flag_emoji' => 'it',
//         'capital' => 'Rome',
//         'native' => 'Italia',
//     ]);

//     $germany = Country::query()->create([
//         'name' => 'Germany',
//         'iso_2' => 'DE',
//         'iso_3' => 'DEU',
//         'phone_code' => '+49',
//         'currency_id' => $currencyId,
//         'flag_emoji' => 'de',
//         'capital' => 'Berlin',
//         'native' => 'Deutschland',
//     ]);

//     CountryTaxClass::query()->create([
//         'country_id' => $italy->getKey(),
//         'tax_class_id' => $taxClass->getKey(),
//         'rate' => 22,
//     ]);

//     CountryTaxClass::query()->create([
//         'country_id' => $germany->getKey(),
//         'tax_class_id' => $taxClass->getKey(),
//         'rate' => 10,
//     ]);

//     $product = Product::factory()->create([
//         'tax_class_id' => $taxClass->getKey(),
//         'active' => true,
//         'visible_from' => null,
//         'visible_until' => null,
//     ]);

//     $product->inventory()->updateOrCreate([], [
//         'price' => 100,
//         'purchase_price' => 20,
//         'price_includes_tax' => false,
//     ]);

//     getJson(
//         config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}",
//         ['country-iso-2' => 'DE']
//     )
//         ->assertOk()
//         ->assertJsonPath('price_calculated.tax_rate', 10)
//         ->assertJsonPath('price_calculated.price_taxable', 100)
//         ->assertJsonPath('price_calculated.price_tax', 10)
//         ->assertJsonPath('price_calculated.price_total', 110)
//         ->assertJsonPath('price_calculated.price_final_taxable', 100)
//         ->assertJsonPath('price_calculated.price_final_tax', 10)
//         ->assertJsonPath('price_calculated.price_final_total', 110);
// })->note('this test works when uncommenting the tax rate calculation in the ProductResource class');

it('calculates product price_calculated by applying automatic discounts', function () {
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $product->inventory()->updateOrCreate([], [
        'price' => 100,
        'purchase_price' => 55,
        'price_includes_tax' => false,
    ]);

    $product->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'name' => 'Automatic 10%',
        'code' => 'AUTO10-PRODUCT-PRICE',
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

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}")
        ->assertOk()
        ->assertJsonPath('price_calculated.price', 100)
        ->assertJsonPath('price_calculated.price_final', 90)
        ->assertJsonPath('price_calculated.purchase_price', 55)
        ->assertJsonPath('price_calculated.price_includes_tax', false)
        ->assertJsonMissingPath('price_calculated.discounts_applied');
});

it('includes pricing source and ordered applied discounts when price_breakdown is requested', function () {
    config()->set('venditio.price_lists.enabled', true);

    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $product->inventory()->updateOrCreate([], [
        'price' => 140,
        'purchase_price' => 60,
        'price_includes_tax' => false,
    ]);

    $retail = PriceList::factory()->create(['name' => 'Retail', 'code' => 'RTL']);
    $wholesale = PriceList::factory()->create(['name' => 'Wholesale', 'code' => 'WHL']);

    $retailPrice = PriceListPrice::factory()->create([
        'product_id' => $product->getKey(),
        'price_list_id' => $retail->getKey(),
        'price' => 130,
        'purchase_price' => 58,
        'is_default' => false,
    ]);

    $wholesalePrice = PriceListPrice::factory()->create([
        'product_id' => $product->getKey(),
        'price_list_id' => $wholesale->getKey(),
        'price' => 100,
        'purchase_price' => 50,
        'is_default' => true,
    ]);

    $category = ProductCategory::factory()->create([
        'active' => true,
    ]);
    $product->categories()->sync([$category->getKey()]);

    $category->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'name' => 'Category 10%',
        'code' => 'CAT10-BREAKDOWN',
        'active' => true,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
        'priority' => 20,
        'stop_after_propagation' => false,
    ]);
    $product->discounts()->create([
        'type' => DiscountType::Fixed,
        'value' => 5,
        'name' => 'Product 5 EUR',
        'code' => 'PRD5-BREAKDOWN',
        'active' => true,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
        'priority' => 10,
        'stop_after_propagation' => false,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}?include=price_breakdown")
        ->assertOk()
        ->assertJsonPath('price_calculated.price', 100)
        ->assertJsonPath('price_calculated.price_final', 85)
        ->assertJsonPath('price_calculated.price_list.name', 'Wholesale')
        ->assertJsonPath('price_calculated.price_source.type', 'price_list')
        ->assertJsonPath('price_calculated.price_source.price_list_price_id', $wholesalePrice->getKey())
        ->assertJsonPath('price_calculated.price_source.price_list.id', $wholesale->getKey())
        ->assertJsonPath('price_calculated.price_source.price_list.code', 'WHL')
        ->assertJsonPath('price_calculated.discounts_applied.0.position', 1)
        ->assertJsonPath('price_calculated.discounts_applied.0.code', 'CAT10-BREAKDOWN')
        ->assertJsonPath('price_calculated.discounts_applied.0.name', 'Category 10%')
        ->assertJsonPath('price_calculated.discounts_applied.0.discountable_type', $category->getMorphClass())
        ->assertJsonPath('price_calculated.discounts_applied.0.discountable_id', $category->getKey())
        ->assertJsonPath('price_calculated.discounts_applied.0.unit_amount', 10)
        ->assertJsonPath('price_calculated.discounts_applied.0.unit_price_before', 100)
        ->assertJsonPath('price_calculated.discounts_applied.0.unit_price_after', 90)
        ->assertJsonPath('price_calculated.discounts_applied.1.position', 2)
        ->assertJsonPath('price_calculated.discounts_applied.1.code', 'PRD5-BREAKDOWN')
        ->assertJsonPath('price_calculated.discounts_applied.1.discountable_type', $product->getMorphClass())
        ->assertJsonPath('price_calculated.discounts_applied.1.discountable_id', $product->getKey())
        ->assertJsonPath('price_calculated.discounts_applied.1.type', 'fixed')
        ->assertJsonPath('price_calculated.discounts_applied.1.unit_amount', 5)
        ->assertJsonPath('price_calculated.discounts_applied.1.unit_price_before', 90)
        ->assertJsonPath('price_calculated.discounts_applied.1.unit_price_after', 85);

    expect($retailPrice->getKey())->not->toBe($wholesalePrice->getKey());
});

it('includes collection scoped discounts in the product price breakdown', function () {
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $product->inventory()->updateOrCreate([], [
        'price' => 100,
        'purchase_price' => 55,
        'price_includes_tax' => false,
    ]);

    $collection = ProductCollection::factory()->create([
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);
    $product->collections()->sync([$collection->getKey()]);

    $collection->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 15,
        'name' => 'Collection 15%',
        'code' => 'COL15-BREAKDOWN',
        'active' => true,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
        'priority' => 30,
        'stop_after_propagation' => false,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}?include=price_breakdown")
        ->assertOk()
        ->assertJsonPath('price_calculated.price', 100)
        ->assertJsonPath('price_calculated.price_final', 85)
        ->assertJsonPath('price_calculated.discounts_applied.0.code', 'COL15-BREAKDOWN')
        ->assertJsonPath('price_calculated.discounts_applied.0.discountable_type', $collection->getMorphClass())
        ->assertJsonPath('price_calculated.discounts_applied.0.discountable_id', $collection->getKey())
        ->assertJsonPath('price_calculated.discounts_applied.0.unit_amount', 15);
});

it('applies multiple propagated discounts to product price_final', function () {
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $product->inventory()->updateOrCreate([], [
        'price' => 100,
        'purchase_price' => 55,
        'price_includes_tax' => false,
    ]);

    $category = ProductCategory::factory()->create([
        'active' => true,
    ]);
    $product->categories()->sync([$category->getKey()]);

    $product->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'name' => 'Product 10%',
        'code' => 'PRD10-PROP',
        'active' => true,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
        'apply_to_cart_total' => false,
        'apply_once_per_cart' => false,
        'stop_after_propagation' => false,
    ]);
    $category->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 50,
        'name' => 'Category 50%',
        'code' => 'CAT50-PROP',
        'active' => true,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
        'apply_to_cart_total' => false,
        'apply_once_per_cart' => false,
        'stop_after_propagation' => false,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}")
        ->assertOk()
        ->assertJsonPath('price_calculated.price', 100)
        ->assertJsonPath('price_calculated.price_final', 45);
});

it('stops discount propagation when stop_after_propagation is enabled', function () {
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $product->inventory()->updateOrCreate([], [
        'price' => 100,
        'purchase_price' => 55,
        'price_includes_tax' => false,
    ]);

    $category = ProductCategory::factory()->create([
        'active' => true,
    ]);
    $product->categories()->sync([$category->getKey()]);

    $category->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 50,
        'name' => 'Category 50% Stop',
        'code' => 'CAT50-STOP',
        'active' => true,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
        'priority' => 10,
        'stop_after_propagation' => true,
    ]);
    $product->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'name' => 'Product 10%',
        'code' => 'PRD10-STOP',
        'active' => true,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
        'priority' => 0,
        'stop_after_propagation' => false,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}")
        ->assertOk()
        ->assertJsonPath('price_calculated.price', 100)
        ->assertJsonPath('price_calculated.price_final', 50);
});

it('filters products index by tags', function () {
    $tagA = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $tagB = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $productWithTagA = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $productWithTagA->tags()->sync([$tagA->getKey()]);

    $productWithTagB = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $productWithTagB->tags()->sync([$tagB->getKey()]);

    $response = getJson(
        config('venditio.routes.api.v1.prefix') . '/products?all=1&tag_ids[]=' . $tagA->getKey()
    )->assertOk();

    $json = $response->json();
    $items = is_array(data_get($json, 'data'))
        ? data_get($json, 'data')
        : $json;

    $ids = collect($items)->pluck('id')->all();

    expect($ids)->toContain($productWithTagA->getKey())
        ->not->toContain($productWithTagB->getKey());
});

it('includes tags relation on products api when requested', function () {
    $tag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $product->tags()->sync([$tag->getKey()]);

    getJson(config('venditio.routes.api.v1.prefix') . '/products/' . $product->getKey() . '?include=tags')
        ->assertOk()
        ->assertJsonPath('tags.0.id', $tag->getKey());
});

it('validates product type compatibility when associating tags to products', function () {
    $productType = ProductType::factory()->create();
    $otherProductType = ProductType::factory()->create();
    $taxClass = TaxClass::factory()->create();
    $tag = Tag::factory()->create([
        'product_type_id' => $otherProductType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'tax_class_id' => $taxClass->getKey(),
        'product_type_id' => $productType->getKey(),
        'name' => 'Tagged product',
        'sku' => 'TAGGED-PRODUCT-001',
        'status' => ProductStatus::Published,
        'tag_ids' => [$tag->getKey()],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['tag_ids']);
});
