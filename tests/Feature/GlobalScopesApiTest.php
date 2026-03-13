<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Brand, Product, ProductCategory, TaxClass};

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('applies active and date range global scopes by default and allows excluding them by query params', function () {
    $inactiveCategory = ProductCategory::factory()->create([
        'active' => false,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $futureCategory = ProductCategory::factory()->create([
        'active' => true,
        'visible_from' => now()->addDay(),
        'visible_until' => null,
    ]);

    $visibleCategory = ProductCategory::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $defaultResponse = getJson(config('venditio.routes.api.v1.prefix') . '/product_categories?all=1')
        ->assertOk();

    $defaultIds = collect($defaultResponse->json())->pluck('id')->all();

    expect($defaultIds)
        ->toContain($visibleCategory->getKey())
        ->not->toContain($inactiveCategory->getKey(), $futureCategory->getKey());

    $excludeActiveResponse = getJson(
        config('venditio.routes.api.v1.prefix') . '/product_categories?all=1&exclude_active_scope=1'
    )->assertOk();

    $excludeActiveIds = collect($excludeActiveResponse->json())->pluck('id')->all();

    expect($excludeActiveIds)
        ->toContain($visibleCategory->getKey(), $inactiveCategory->getKey())
        ->not->toContain($futureCategory->getKey());

    $excludeDateResponse = getJson(
        config('venditio.routes.api.v1.prefix') . '/product_categories?all=1&exclude_date_range_scope=1'
    )->assertOk();

    $excludeDateIds = collect($excludeDateResponse->json())->pluck('id')->all();

    expect($excludeDateIds)
        ->toContain($visibleCategory->getKey(), $futureCategory->getKey())
        ->not->toContain($inactiveCategory->getKey());

    $excludeAllResponse = getJson(
        config('venditio.routes.api.v1.prefix') . '/product_categories?all=1&exclude_all_scopes=1'
    )->assertOk();

    $excludeAllIds = collect($excludeAllResponse->json())->pluck('id')->all();

    expect($excludeAllIds)->toContain(
        $visibleCategory->getKey(),
        $inactiveCategory->getKey(),
        $futureCategory->getKey()
    );
});

it('filters product index by active statuses and allows bypassing with exclude_all_scopes', function () {
    $taxClass = TaxClass::factory()->create();

    $published = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $draft = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Draft,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $defaultResponse = getJson(config('venditio.routes.api.v1.prefix') . '/products?all=1')
        ->assertOk();

    $defaultJson = $defaultResponse->json();
    $defaultItems = is_array(data_get($defaultJson, 'data'))
        ? data_get($defaultJson, 'data')
        : $defaultJson;

    $defaultIds = collect($defaultItems)->pluck('id')->all();

    expect($defaultIds)
        ->toContain($published->getKey())
        ->not->toContain($draft->getKey());

    $excludeAllResponse = getJson(
        config('venditio.routes.api.v1.prefix') . '/products?all=1&exclude_all_scopes=1&include_variants=1'
    )->assertOk();

    $excludeAllJson = $excludeAllResponse->json();
    $excludeAllItems = is_array(data_get($excludeAllJson, 'data'))
        ? data_get($excludeAllJson, 'data')
        : $excludeAllJson;

    $excludeAllIds = collect($excludeAllItems)->pluck('id')->all();

    expect($excludeAllIds)->toContain($published->getKey(), $draft->getKey());
});

it('allows explicit status filters to override the product status global scope', function () {
    $taxClass = TaxClass::factory()->create();

    $draft = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Draft,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = getJson(
        config('venditio.routes.api.v1.prefix') . '/products?all=1&include_variants=1&status=draft'
    )->assertOk();

    $json = $response->json();
    $items = is_array(data_get($json, 'data'))
        ? data_get($json, 'data')
        : $json;

    expect(collect($items)->pluck('id')->all())->toBe([$draft->getKey()]);
});

it('allows explicit active filters to override the active global scope', function () {
    $inactiveCategory = ProductCategory::factory()->create([
        'active' => false,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    ProductCategory::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = getJson(
        config('venditio.routes.api.v1.prefix') . '/product_categories?all=1&active=0'
    )->assertOk();

    expect(collect($response->json())->pluck('id')->all())->toBe([$inactiveCategory->getKey()]);
});

it('applies the active global scope to brands by default and allows excluding it', function () {
    $inactiveBrand = Brand::factory()->create([
        'active' => false,
    ]);

    $activeBrand = Brand::factory()->create([
        'active' => true,
    ]);

    $defaultResponse = getJson(config('venditio.routes.api.v1.prefix') . '/brands?all=1')
        ->assertOk();

    expect(collect($defaultResponse->json())->pluck('id')->all())
        ->toContain($activeBrand->getKey())
        ->not->toContain($inactiveBrand->getKey());

    $excludeScopeResponse = getJson(
        config('venditio.routes.api.v1.prefix') . '/brands?all=1&exclude_active_scope=1'
    )->assertOk();

    expect(collect($excludeScopeResponse->json())->pluck('id')->all())
        ->toContain($activeBrand->getKey(), $inactiveBrand->getKey());
});

it('allows explicit date filters to override the date range global scope', function () {
    $futureCategory = ProductCategory::factory()->create([
        'active' => true,
        'visible_from' => now()->addDay(),
        'visible_until' => null,
    ]);

    ProductCategory::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = getJson(
        config('venditio.routes.api.v1.prefix')
        . '/product_categories?all=1&visible_from_start='
        . urlencode($futureCategory->visible_from?->toDateTimeString() ?? '')
    )->assertOk();

    expect(collect($response->json())->pluck('id')->all())->toContain($futureCategory->getKey());
});
