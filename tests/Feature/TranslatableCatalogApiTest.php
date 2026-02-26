<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Brand, Product, ProductCategory, ProductType, TaxClass};

use function Pest\Laravel\{assertDatabaseHas, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('translatable.locales', ['en', 'it']);
    app()->setLocale('en');
});

it('supports locale keyed payloads for product types', function () {
    $response = postJson(config('venditio.routes.api.v1.prefix') . '/product_types', [
        'en' => [
            'name' => 'Food',
        ],
        'it' => [
            'name' => 'Cibo',
        ],
    ])->assertCreated()
        ->assertJsonFragment([
            'name' => 'Food',
            'slug' => 'food',
        ]);

    $productTypeId = $response->json('id');

    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductType)->getMorphClass(),
        'translatable_id' => $productTypeId,
        'locale' => 'it',
        'attribute' => 'name',
        'value' => 'Cibo',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductType)->getMorphClass(),
        'translatable_id' => $productTypeId,
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'cibo',
    ]);

    getJson(
        config('venditio.routes.api.v1.prefix') . "/product_types/{$productTypeId}",
        ['Locale' => 'it']
    )->assertOk()
        ->assertJsonFragment([
            'name' => 'Cibo',
            'slug' => 'cibo',
        ]);
});

it('supports translations wrapper payloads for brands', function () {
    $response = postJson(config('venditio.routes.api.v1.prefix') . '/brands', [
        'translations' => [
            'en' => [
                'name' => 'Shoes Factory',
            ],
            'it' => [
                'name' => 'Fabbrica Scarpe',
            ],
        ],
    ])->assertCreated()
        ->assertJsonFragment([
            'name' => 'Shoes Factory',
            'slug' => 'shoes-factory',
        ]);

    $brandId = $response->json('id');

    assertDatabaseHas('translations', [
        'translatable_type' => (new Brand)->getMorphClass(),
        'translatable_id' => $brandId,
        'locale' => 'it',
        'attribute' => 'name',
        'value' => 'Fabbrica Scarpe',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new Brand)->getMorphClass(),
        'translatable_id' => $brandId,
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'fabbrica-scarpe',
    ]);

    getJson(
        config('venditio.routes.api.v1.prefix') . "/brands/{$brandId}",
        ['Locale' => 'it']
    )->assertOk()
        ->assertJsonFragment([
            'name' => 'Fabbrica Scarpe',
            'slug' => 'fabbrica-scarpe',
        ]);
});

it('supports translated fields for products', function () {
    $taxClass = TaxClass::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'tax_class_id' => $taxClass->getKey(),
        'sku' => 'SKU-TRANS-001',
        'status' => ProductStatus::Published,
        'en' => [
            'name' => 'Apple Juice',
            'description' => 'Fresh apple juice',
            'description_short' => 'Juice',
        ],
        'it' => [
            'name' => 'Succo di mela',
            'description' => 'Succo fresco di mela',
            'description_short' => 'Succo',
        ],
    ])->assertCreated()
        ->assertJsonFragment([
            'name' => 'Apple Juice',
            'slug' => 'apple-juice',
            'description' => 'Fresh apple juice',
            'description_short' => 'Juice',
        ]);

    $productId = $response->json('id');

    assertDatabaseHas('products', [
        'id' => $productId,
        'sku' => 'SKU-TRANS-001',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'it',
        'attribute' => 'name',
        'value' => 'Succo di mela',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'succo-di-mela',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'it',
        'attribute' => 'description',
        'value' => 'Succo fresco di mela',
    ]);

    getJson(
        config('venditio.routes.api.v1.prefix') . "/products/{$productId}",
        ['Locale' => 'it']
    )->assertOk()
        ->assertJsonFragment([
            'name' => 'Succo di mela',
            'slug' => 'succo-di-mela',
            'description' => 'Succo fresco di mela',
            'description_short' => 'Succo',
        ]);
});

it('stores product name and slug translations on create and when adding a new locale on update', function () {
    config()->set('translatable.locales', ['en', 'it', 'fr']);

    $taxClass = TaxClass::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'tax_class_id' => $taxClass->getKey(),
        'sku' => 'SKU-MULTI-TRANS-001',
        'status' => ProductStatus::Published,
        'en' => [
            'name' => 'Sparkling Water',
        ],
        'it' => [
            'name' => 'Acqua Frizzante',
        ],
    ])->assertCreated();

    $productId = $response->json('id');

    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'en',
        'attribute' => 'name',
        'value' => 'Sparkling Water',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'en',
        'attribute' => 'slug',
        'value' => 'sparkling-water',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'it',
        'attribute' => 'name',
        'value' => 'Acqua Frizzante',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'acqua-frizzante',
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/products/{$productId}", [
        'fr' => [
            'name' => 'Eau Gazeuse',
        ],
    ])->assertOk();

    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'fr',
        'attribute' => 'name',
        'value' => 'Eau Gazeuse',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'fr',
        'attribute' => 'slug',
        'value' => 'eau-gazeuse',
    ]);

    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'en',
        'attribute' => 'name',
        'value' => 'Sparkling Water',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'en',
        'attribute' => 'slug',
        'value' => 'sparkling-water',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'it',
        'attribute' => 'name',
        'value' => 'Acqua Frizzante',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'acqua-frizzante',
    ]);
});

it('supports translated names for product categories', function () {
    $response = postJson(config('venditio.routes.api.v1.prefix') . '/product_categories', [
        'sort_order' => 1,
        'name:en' => 'Clothing',
        'name:it' => 'Abbigliamento',
    ])->assertCreated()
        ->assertJsonFragment([
            'name' => 'Clothing',
            'slug' => 'clothing',
        ]);

    $categoryId = $response->json('id');

    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductCategory)->getMorphClass(),
        'translatable_id' => $categoryId,
        'locale' => 'it',
        'attribute' => 'name',
        'value' => 'Abbigliamento',
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductCategory)->getMorphClass(),
        'translatable_id' => $categoryId,
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'abbigliamento',
    ]);

    getJson(
        config('venditio.routes.api.v1.prefix') . "/product_categories/{$categoryId}",
        ['Locale' => 'it']
    )->assertOk()
        ->assertJsonFragment([
            'name' => 'Abbigliamento',
            'slug' => 'abbigliamento',
        ]);
});

it('keeps translated slugs in sync on update', function () {
    $productTypeResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_types', [
        'en' => [
            'name' => 'Food',
        ],
        'it' => [
            'name' => 'Cibo',
        ],
    ])->assertCreated();

    $productTypeId = $productTypeResponse->json('id');

    patchJson(config('venditio.routes.api.v1.prefix') . "/product_types/{$productTypeId}", [
        'en' => [
            'name' => 'Fresh Food',
        ],
        'it' => [
            'name' => 'Cibo Fresco',
        ],
    ])->assertOk()
        ->assertJsonFragment([
            'slug' => 'fresh-food',
        ]);

    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductType)->getMorphClass(),
        'translatable_id' => $productTypeId,
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'cibo-fresco',
    ]);

    $brandResponse = postJson(config('venditio.routes.api.v1.prefix') . '/brands', [
        'en' => [
            'name' => 'Shoes Factory',
        ],
        'it' => [
            'name' => 'Fabbrica Scarpe',
        ],
    ])->assertCreated();

    $brandId = $brandResponse->json('id');

    patchJson(config('venditio.routes.api.v1.prefix') . "/brands/{$brandId}", [
        'en' => [
            'name' => 'Leather Shoes Factory',
        ],
        'it' => [
            'name' => 'Fabbrica Scarpe in Pelle',
        ],
    ])->assertOk()
        ->assertJsonFragment([
            'slug' => 'leather-shoes-factory',
        ]);

    assertDatabaseHas('translations', [
        'translatable_type' => (new Brand)->getMorphClass(),
        'translatable_id' => $brandId,
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'fabbrica-scarpe-in-pelle',
    ]);

    $taxClass = TaxClass::factory()->create();

    $productResponse = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'tax_class_id' => $taxClass->getKey(),
        'sku' => 'SKU-TRANS-002',
        'status' => ProductStatus::Published,
        'en' => [
            'name' => 'Apple Juice',
        ],
        'it' => [
            'name' => 'Succo di mela',
        ],
    ])->assertCreated();

    $productId = $productResponse->json('id');

    patchJson(config('venditio.routes.api.v1.prefix') . "/products/{$productId}", [
        'en' => [
            'name' => 'Orange Juice',
        ],
        'it' => [
            'name' => 'Succo di arancia',
        ],
    ])->assertOk()
        ->assertJsonFragment([
            'slug' => 'orange-juice',
        ]);

    assertDatabaseHas('translations', [
        'translatable_type' => (new Product)->getMorphClass(),
        'translatable_id' => $productId,
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'succo-di-arancia',
    ]);

    $categoryResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_categories', [
        'sort_order' => 1,
        'en' => [
            'name' => 'Clothing',
        ],
        'it' => [
            'name' => 'Abbigliamento',
        ],
    ])->assertCreated();

    $categoryId = $categoryResponse->json('id');

    patchJson(config('venditio.routes.api.v1.prefix') . "/product_categories/{$categoryId}", [
        'en' => [
            'name' => 'Smart Clothing',
        ],
        'it' => [
            'name' => 'Abbigliamento Smart',
        ],
    ])->assertOk()
        ->assertJsonFragment([
            'slug' => 'smart-clothing',
        ]);

    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductCategory)->getMorphClass(),
        'translatable_id' => $categoryId,
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'abbigliamento-smart',
    ]);
});

it('allows duplicate translated names for product types while keeping base slug unique', function () {
    postJson(config('venditio.routes.api.v1.prefix') . '/product_types', [
        'en' => [
            'name' => 'Food',
        ],
        'it' => [
            'name' => 'Cibo',
        ],
    ])->assertCreated()
        ->assertJsonFragment([
            'slug' => 'food',
        ]);

    $secondResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_types', [
        'en' => [
            'name' => 'Food',
        ],
        'it' => [
            'name' => 'Cibo',
        ],
    ])->assertCreated()
        ->assertJsonFragment([
            'slug' => 'food-1',
        ]);

    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductType)->getMorphClass(),
        'translatable_id' => $secondResponse->json('id'),
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'cibo',
    ]);
});

it('allows duplicate translated names for product types on update while keeping base slug unique', function () {
    postJson(config('venditio.routes.api.v1.prefix') . '/product_types', [
        'en' => [
            'name' => 'Food',
        ],
        'it' => [
            'name' => 'Cibo',
        ],
    ])->assertCreated();

    $productTypeResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_types', [
        'en' => [
            'name' => 'Drinks',
        ],
        'it' => [
            'name' => 'Bevande',
        ],
    ])->assertCreated();

    patchJson(config('venditio.routes.api.v1.prefix') . '/product_types/' . $productTypeResponse->json('id'), [
        'en' => [
            'name' => 'Food',
        ],
        'it' => [
            'name' => 'Cibo',
        ],
    ])->assertOk()
        ->assertJsonFragment([
            'slug' => 'food-1',
        ]);

    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductType)->getMorphClass(),
        'translatable_id' => $productTypeResponse->json('id'),
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'cibo',
    ]);
});

it('allows duplicate translated names for brands while keeping base slug unique', function () {
    postJson(config('venditio.routes.api.v1.prefix') . '/brands', [
        'en' => [
            'name' => 'Shoes Factory',
        ],
        'it' => [
            'name' => 'Fabbrica Scarpe',
        ],
    ])->assertCreated()
        ->assertJsonFragment([
            'slug' => 'shoes-factory',
        ]);

    $secondResponse = postJson(config('venditio.routes.api.v1.prefix') . '/brands', [
        'en' => [
            'name' => 'Shoes Factory',
        ],
        'it' => [
            'name' => 'Fabbrica Scarpe',
        ],
    ])->assertCreated()
        ->assertJsonFragment([
            'slug' => 'shoes-factory-1',
        ]);

    assertDatabaseHas('translations', [
        'translatable_type' => (new Brand)->getMorphClass(),
        'translatable_id' => $secondResponse->json('id'),
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'fabbrica-scarpe',
    ]);
});

it('allows duplicate translated names for brands on update while keeping base slug unique', function () {
    postJson(config('venditio.routes.api.v1.prefix') . '/brands', [
        'en' => [
            'name' => 'Shoes Factory',
        ],
        'it' => [
            'name' => 'Fabbrica Scarpe',
        ],
    ])->assertCreated();

    $brandResponse = postJson(config('venditio.routes.api.v1.prefix') . '/brands', [
        'en' => [
            'name' => 'Leather House',
        ],
        'it' => [
            'name' => 'Casa in Pelle',
        ],
    ])->assertCreated();

    patchJson(config('venditio.routes.api.v1.prefix') . '/brands/' . $brandResponse->json('id'), [
        'en' => [
            'name' => 'Shoes Factory',
        ],
        'it' => [
            'name' => 'Fabbrica Scarpe',
        ],
    ])->assertOk()
        ->assertJsonFragment([
            'slug' => 'shoes-factory-1',
        ]);

    assertDatabaseHas('translations', [
        'translatable_type' => (new Brand)->getMorphClass(),
        'translatable_id' => $brandResponse->json('id'),
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'fabbrica-scarpe',
    ]);
});

it('allows duplicate translated names for product categories while keeping base slug unique', function () {
    postJson(config('venditio.routes.api.v1.prefix') . '/product_categories', [
        'sort_order' => 1,
        'en' => [
            'name' => 'Clothing',
        ],
        'it' => [
            'name' => 'Abbigliamento',
        ],
    ])->assertCreated()
        ->assertJsonFragment([
            'slug' => 'clothing',
        ]);

    $secondResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_categories', [
        'sort_order' => 2,
        'en' => [
            'name' => 'Clothing',
        ],
        'it' => [
            'name' => 'Abbigliamento',
        ],
    ])->assertCreated()
        ->assertJsonFragment([
            'slug' => 'clothing-1',
        ]);

    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductCategory)->getMorphClass(),
        'translatable_id' => $secondResponse->json('id'),
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'abbigliamento',
    ]);
});

it('allows duplicate translated names for product categories on update while keeping base slug unique', function () {
    postJson(config('venditio.routes.api.v1.prefix') . '/product_categories', [
        'sort_order' => 1,
        'en' => [
            'name' => 'Clothing',
        ],
        'it' => [
            'name' => 'Abbigliamento',
        ],
    ])->assertCreated();

    $categoryResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_categories', [
        'sort_order' => 2,
        'en' => [
            'name' => 'Accessories',
        ],
        'it' => [
            'name' => 'Accessori',
        ],
    ])->assertCreated();

    patchJson(config('venditio.routes.api.v1.prefix') . '/product_categories/' . $categoryResponse->json('id'), [
        'en' => [
            'name' => 'Clothing',
        ],
        'it' => [
            'name' => 'Abbigliamento',
        ],
    ])->assertOk()
        ->assertJsonFragment([
            'slug' => 'clothing-1',
        ]);

    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductCategory)->getMorphClass(),
        'translatable_id' => $categoryResponse->json('id'),
        'locale' => 'it',
        'attribute' => 'slug',
        'value' => 'abbigliamento',
    ]);
});
