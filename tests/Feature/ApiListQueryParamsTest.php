<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Models\{Brand, PriceList, Tag};

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

function apiListData(array $json): array
{
    return is_array(data_get($json, 'data'))
        ? data_get($json, 'data')
        : $json;
}

it('filters list endpoints by id array query param', function () {
    $brandA = Brand::factory()->create();
    $brandB = Brand::factory()->create();
    $brandC = Brand::factory()->create();

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/brands?all=1&id[]=' . $brandA->getKey() . '&id[]=' . $brandC->getKey())
        ->assertOk();

    $ids = collect(apiListData($response->json()))
        ->pluck('id')
        ->all();

    expect($ids)->toEqualCanonicalizing([$brandA->getKey(), $brandC->getKey()])
        ->not->toContain($brandB->getKey());
});

it('filters brands by related tag ids', function () {
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
    $brandWithTagA = Brand::factory()->create();
    $brandWithTagB = Brand::factory()->create();

    $brandWithTagA->tags()->sync([$tagA->getKey()]);
    $brandWithTagB->tags()->sync([$tagB->getKey()]);

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/brands?all=1&tag_ids[]=' . $tagA->getKey())
        ->assertOk();

    $ids = collect(apiListData($response->json()))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($brandWithTagA->getKey())
        ->not->toContain($brandWithTagB->getKey());
});

it('filters brands by category-style catalog columns', function () {
    $menuBrand = Brand::factory()->create([
        'show_in_menu' => true,
        'in_evidence' => false,
        'sort_order' => 10,
    ]);

    Brand::factory()->create([
        'show_in_menu' => false,
        'in_evidence' => true,
        'sort_order' => 20,
    ]);

    $response = getJson(
        config('venditio.routes.api.v1.prefix') . '/brands?all=1&show_in_menu=1&sort_order=10'
    )->assertOk();

    $ids = collect(apiListData($response->json()))
        ->pluck('id')
        ->all();

    expect($ids)->toBe([$menuBrand->getKey()]);
});

it('filters string columns with case-insensitive partial matching', function () {
    $matchingBrand = Brand::factory()->create([
        'name' => 'Acme Premium Goods',
    ]);

    $nonMatchingBrand = Brand::factory()->create([
        'name' => 'Northwind Supplies',
    ]);

    $response = getJson(
        config('venditio.routes.api.v1.prefix') . '/brands?all=1&name=' . urlencode('premium')
    )->assertOk();

    $ids = collect(apiListData($response->json()))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($matchingBrand->getKey())
        ->not->toContain($nonMatchingBrand->getKey());
});

it('supports pagination and sorting query params on list endpoints', function () {
    Brand::factory()->count(5)->create();

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/brands?sort_by=id&sort_dir=desc&per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 2);

    $expectedIds = Brand::query()
        ->orderByDesc('id')
        ->skip(2)
        ->take(2)
        ->pluck('id')
        ->all();

    $responseIds = collect($response->json('data'))
        ->pluck('id')
        ->all();

    expect($responseIds)->toBe($expectedIds);
});

it('supports boolean alias filters such as is_active when the resource has active column', function () {
    config()->set('venditio.price_lists.enabled', true);

    $active = PriceList::factory()->create(['active' => true]);
    $inactive = PriceList::factory()->create(['active' => false]);

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/price_lists?all=1&is_active=1')
        ->assertOk();

    $ids = collect(apiListData($response->json()))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($active->getKey())
        ->not->toContain($inactive->getKey());
});

it('supports date range filters through column_start and column_end query params', function () {
    config()->set('venditio.price_lists.enabled', true);

    $old = PriceList::factory()->create([
        'created_at' => now()->subDays(10),
    ]);

    $recent = PriceList::factory()->create([
        'created_at' => now()->subDay(),
    ]);

    $response = getJson(
        config('venditio.routes.api.v1.prefix') . '/price_lists?all=1&created_at_start=' . urlencode(now()->subDays(3)->toDateTimeString())
    )->assertOk();

    $ids = collect(apiListData($response->json()))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($recent->getKey())
        ->not->toContain($old->getKey());
});

it('validates query params and rejects unknown or invalid query values', function () {
    getJson(config('venditio.routes.api.v1.prefix') . '/brands?unknown_param=1')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['unknown_param']);

    getJson(config('venditio.routes.api.v1.prefix') . '/brands?sort_by=not_a_column&sort_dir=invalid&page=0&per_page=invalid')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort_by', 'sort_dir', 'page', 'per_page']);
});

it('includes soft deleted records when with_trashed is true on soft deletable models', function () {
    $active = Brand::factory()->create();
    $deleted = Brand::factory()->create();
    $deleted->delete();

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/brands?all=1&with_trashed=1')
        ->assertOk();

    $ids = collect(apiListData($response->json()))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($active->getKey(), $deleted->getKey());
});

it('returns only soft deleted records when only_trashed is true on soft deletable models', function () {
    $active = Brand::factory()->create();
    $deleted = Brand::factory()->create();
    $deleted->delete();

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/brands?all=1&only_trashed=1')
        ->assertOk();

    $ids = collect(apiListData($response->json()))
        ->pluck('id')
        ->all();

    expect($ids)->toContain($deleted->getKey())
        ->not->toContain($active->getKey());
});

it('rejects soft delete query params on models that do not support soft deletes', function () {
    getJson(config('venditio.routes.api.v1.prefix') . '/country_tax_classes?with_trashed=1')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['with_trashed']);

    getJson(config('venditio.routes.api.v1.prefix') . '/country_tax_classes?only_trashed=1')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['only_trashed']);
});
