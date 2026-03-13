<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\TaxClass;

use function Pest\Laravel\{getJson, patchJson, postJson};

uses(RefreshDatabase::class);

it('resolves slug-enabled catalog resources by slug', function () {
    $productTypeResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_types', [
        'name' => 'Trail Shoes',
        'active' => true,
    ])->assertCreated();

    getJson(config('venditio.routes.api.v1.prefix') . '/product_types/' . $productTypeResponse->json('slug'))
        ->assertOk()
        ->assertJsonPath('id', $productTypeResponse->json('id'));

    $brandResponse = postJson(config('venditio.routes.api.v1.prefix') . '/brands', [
        'name' => 'Nord Ridge',
        'sort_order' => 1,
    ])->assertCreated();

    getJson(config('venditio.routes.api.v1.prefix') . '/brands/' . $brandResponse->json('slug'))
        ->assertOk()
        ->assertJsonPath('id', $brandResponse->json('id'));

    $categoryResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_categories', [
        'name' => 'Outdoor',
        'active' => true,
        'sort_order' => 1,
    ])->assertCreated();

    getJson(config('venditio.routes.api.v1.prefix') . '/product_categories/' . $categoryResponse->json('slug'))
        ->assertOk()
        ->assertJsonPath('id', $categoryResponse->json('id'));
});

it('resolves slug-enabled catalog resources by translated slug in any locale', function () {
    config()->set('translatable.locales', ['en', 'it']);
    app()->setLocale('en');

    $productTypeResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_types', [
        'en' => [
            'name' => 'Food',
        ],
        'it' => [
            'name' => 'Cibo',
        ],
    ])->assertCreated();

    getJson(
        config('venditio.routes.api.v1.prefix') . '/product_types/cibo',
        ['Locale' => 'it']
    )->assertOk()
        ->assertJsonPath('id', $productTypeResponse->json('id'));

    $brandResponse = postJson(config('venditio.routes.api.v1.prefix') . '/brands', [
        'translations' => [
            'en' => [
                'name' => 'Shoes Factory',
            ],
            'it' => [
                'name' => 'Fabbrica Scarpe',
            ],
        ],
        'sort_order' => 1,
    ])->assertCreated();

    getJson(
        config('venditio.routes.api.v1.prefix') . '/brands/fabbrica-scarpe',
        ['Locale' => 'it']
    )->assertOk()
        ->assertJsonPath('id', $brandResponse->json('id'));

    $categoryResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_categories', [
        'sort_order' => 1,
        'en' => [
            'name' => 'Clothing',
        ],
        'it' => [
            'name' => 'Abbigliamento',
        ],
    ])->assertCreated();

    getJson(
        config('venditio.routes.api.v1.prefix') . '/product_categories/abbigliamento',
        ['Locale' => 'it']
    )->assertOk()
        ->assertJsonPath('id', $categoryResponse->json('id'));
});

it('resolves products by slug for show, update and variants routes', function () {
    $taxClass = TaxClass::factory()->create();

    $productResponse = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Pro Runner',
        'sku' => 'SLUG-BINDING-PRODUCT-001',
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ])->assertCreated();

    $productId = $productResponse->json('id');
    $productSlug = $productResponse->json('slug');

    $variantResponse = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'parent_id' => $productId,
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Pro Runner Blue',
        'sku' => 'SLUG-BINDING-PRODUCT-002',
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ])->assertCreated();

    getJson(config('venditio.routes.api.v1.prefix') . '/products/' . $productSlug)
        ->assertOk()
        ->assertJsonPath('id', $productId);

    getJson(config('venditio.routes.api.v1.prefix') . '/products/' . $productSlug . '/variants')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $variantResponse->json('id'),
        ]);

    patchJson(config('venditio.routes.api.v1.prefix') . '/products/' . $productSlug, [
        'name' => 'Pro Runner Updated',
    ])->assertOk()
        ->assertJsonPath('id', $productId);
});

it('resolves products by translated slug for show, update and variants routes', function () {
    config()->set('translatable.locales', ['en', 'it']);
    app()->setLocale('en');

    $taxClass = TaxClass::factory()->create();

    $productResponse = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'tax_class_id' => $taxClass->getKey(),
        'sku' => 'SLUG-BINDING-PRODUCT-003',
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'en' => [
            'name' => 'Apple Juice',
        ],
        'it' => [
            'name' => 'Succo di mela',
        ],
    ])->assertCreated();

    $productId = $productResponse->json('id');
    $productItSlug = 'succo-di-mela';

    $variantResponse = postJson(config('venditio.routes.api.v1.prefix') . '/products', [
        'parent_id' => $productId,
        'tax_class_id' => $taxClass->getKey(),
        'sku' => 'SLUG-BINDING-PRODUCT-004',
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'en' => [
            'name' => 'Apple Juice Blue',
        ],
        'it' => [
            'name' => 'Succo di mela blu',
        ],
    ])->assertCreated();

    getJson(
        config('venditio.routes.api.v1.prefix') . '/products/' . $productItSlug,
        ['Locale' => 'it']
    )->assertOk()
        ->assertJsonPath('id', $productId);

    getJson(
        config('venditio.routes.api.v1.prefix') . '/products/' . $productItSlug . '/variants',
        ['Locale' => 'it']
    )->assertOk()
        ->assertJsonFragment([
            'id' => $variantResponse->json('id'),
        ]);

    patchJson(
        config('venditio.routes.api.v1.prefix') . '/products/' . $productItSlug,
        [
            'it' => [
                'name' => 'Succo di mela premium',
            ],
        ],
        ['Locale' => 'it']
    )->assertOk()
        ->assertJsonPath('id', $productId);
});
