<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Product, ProductCategory, TaxClass};

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
