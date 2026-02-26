<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Models\{Brand, PriceList};

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
