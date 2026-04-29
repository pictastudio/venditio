<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PictaStudio\Venditio\Models\{Brand, Product, Tag};

use function Pest\Laravel\{assertDatabaseHas, assertDatabaseMissing, getJson, patch, patchJson, post, postJson};

uses(RefreshDatabase::class);

it('stores and exposes category-style catalog fields on brands', function () {
    $response = postJson(config('venditio.routes.api.v1.prefix') . '/brands', [
        'name' => 'Outdoor Lab',
        'abstract' => 'Brand abstract',
        'description' => 'Brand description',
        'metadata' => ['seo' => ['title' => 'Outdoor Lab']],
        'active' => true,
        'show_in_menu' => true,
        'in_evidence' => true,
        'sort_order' => 1,
    ])->assertCreated()
        ->assertJsonFragment([
            'name' => 'Outdoor Lab',
            'abstract' => 'Brand abstract',
            'description' => 'Brand description',
            'active' => true,
            'show_in_menu' => true,
            'in_evidence' => true,
            'sort_order' => 1,
        ]);

    assertDatabaseHas('brands', [
        'id' => $response->json('id'),
        'active' => true,
        'show_in_menu' => true,
        'in_evidence' => true,
        'sort_order' => 1,
    ]);
});

it('stores a brand with tags', function () {
    $tag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/brands?include=tags', [
        'name' => 'Tagged Brand',
        'active' => true,
        'sort_order' => 1,
        'tag_ids' => [$tag->getKey()],
    ])->assertCreated()
        ->assertJsonPath('tags.0.id', $tag->getKey());

    assertDatabaseHas('taggables', [
        'tag_id' => $tag->getKey(),
        'taggable_type' => (new Brand)->getMorphClass(),
        'taggable_id' => $response->json('id'),
    ]);
});

it('updates brand tags using sync semantics', function () {
    $firstTag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $secondTag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $brand = Brand::factory()->create([
        'active' => true,
    ]);

    $brand->tags()->sync([$firstTag->getKey()]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/brands/{$brand->getKey()}?include=tags", [
        'tag_ids' => [$secondTag->getKey()],
    ])->assertOk()
        ->assertJsonPath('tags.0.id', $secondTag->getKey());

    assertDatabaseMissing('taggables', [
        'tag_id' => $firstTag->getKey(),
        'taggable_type' => $brand->getMorphClass(),
        'taggable_id' => $brand->getKey(),
    ]);
    assertDatabaseHas('taggables', [
        'tag_id' => $secondTag->getKey(),
        'taggable_type' => $brand->getMorphClass(),
        'taggable_id' => $brand->getKey(),
    ]);
});

it('uploads brand images as a typed images collection on update', function () {
    Storage::fake('public');

    $brand = Brand::factory()->create([
        'active' => true,
    ]);

    patch(
        config('venditio.routes.api.v1.prefix') . '/brands/' . $brand->getKey(),
        [
            'images' => [
                [
                    'file' => UploadedFile::fake()->image('thumb.jpg'),
                    'alt' => 'thumb',
                    'name' => 'Thumb',
                    'type' => 'thumb',
                ],
                [
                    'file' => UploadedFile::fake()->image('cover.jpg'),
                    'alt' => 'cover',
                    'name' => 'Cover',
                    'type' => 'cover',
                ],
            ],
        ],
        ['Accept' => 'application/json']
    )->assertOk()
        ->assertJsonPath('images.0.type', 'thumb')
        ->assertJsonPath('images.1.type', 'cover');

    $brand->refresh();

    $thumb = collect($brand->images)->firstWhere('type', 'thumb');
    $cover = collect($brand->images)->firstWhere('type', 'cover');

    expect($brand->images)->toBeArray()->toHaveCount(2)
        ->and(data_get($thumb, 'type'))->toBe('thumb')
        ->and(data_get($cover, 'type'))->toBe('cover')
        ->and(str_starts_with((string) data_get($thumb, 'src'), 'brands/' . $brand->getKey() . '/thumb/'))
        ->toBeTrue()
        ->and(str_starts_with((string) data_get($cover, 'src'), 'brands/' . $brand->getKey() . '/cover/'))
        ->toBeTrue();

    Storage::disk('public')->assertExists((string) data_get($thumb, 'src'));
    Storage::disk('public')->assertExists((string) data_get($cover, 'src'));
});

it('stores brand images with sort_order and returns them in the persisted order', function () {
    Storage::fake('public');

    $response = post(
        config('venditio.routes.api.v1.prefix') . '/brands',
        [
            'name' => 'Ordered Brand',
            'active' => true,
            'sort_order' => 1,
            'images' => [
                [
                    'file' => UploadedFile::fake()->image('thumb.jpg'),
                    'alt' => 'thumb',
                    'name' => 'Thumb',
                    'type' => 'thumb',
                    'sort_order' => 20,
                ],
                [
                    'file' => UploadedFile::fake()->image('cover.jpg'),
                    'alt' => 'cover',
                    'name' => 'Cover',
                    'type' => 'cover',
                    'sort_order' => 10,
                ],
            ],
        ],
        ['Accept' => 'application/json']
    )->assertCreated()
        ->assertJsonPath('images.0.type', 'cover')
        ->assertJsonPath('images.0.sort_order', 10)
        ->assertJsonPath('images.1.type', 'thumb')
        ->assertJsonPath('images.1.sort_order', 20);

    $brand = Brand::query()->findOrFail($response->json('id'));

    expect(data_get($brand->images, '0.type'))->toBe('cover')
        ->and(data_get($brand->images, '0.sort_order'))->toBe(10)
        ->and(data_get($brand->images, '1.type'))->toBe('thumb')
        ->and(data_get($brand->images, '1.sort_order'))->toBe(20);
});

it('allows multiple brand images with null type', function () {
    Storage::fake('public');

    $brand = Brand::factory()->create([
        'active' => true,
    ]);

    patch(
        config('venditio.routes.api.v1.prefix') . '/brands/' . $brand->getKey(),
        [
            'images' => [
                [
                    'file' => UploadedFile::fake()->image('gallery-a.jpg'),
                    'type' => null,
                    'sort_order' => 10,
                ],
                [
                    'file' => UploadedFile::fake()->image('gallery-b.jpg'),
                    'sort_order' => 20,
                ],
            ],
        ],
        ['Accept' => 'application/json']
    )->assertOk()
        ->assertJsonCount(2, 'images')
        ->assertJsonPath('images.0.type', null)
        ->assertJsonPath('images.1.type', null);

    $brand->refresh();

    expect($brand->images)->toHaveCount(2)
        ->and(str_starts_with((string) data_get($brand->images, '0.src'), 'brands/' . $brand->getKey() . '/images/'))->toBeTrue()
        ->and(str_starts_with((string) data_get($brand->images, '1.src'), 'brands/' . $brand->getKey() . '/images/'))->toBeTrue();
});

it('updates brand image sort_order without requiring a new upload', function () {
    $brand = Brand::factory()->create([
        'active' => true,
        'images' => [
            [
                'id' => 'thumb-image',
                'type' => 'thumb',
                'alt' => 'Thumb',
                'mimetype' => 'image/jpeg',
                'sort_order' => 20,
                'src' => 'brands/thumb.jpg',
            ],
            [
                'id' => 'cover-image',
                'type' => 'cover',
                'alt' => 'Cover',
                'mimetype' => 'image/jpeg',
                'sort_order' => 10,
                'src' => 'brands/cover.jpg',
            ],
        ],
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/brands/{$brand->getKey()}", [
        'images' => [
            [
                'id' => 'thumb-image',
                'type' => 'thumb',
                'sort_order' => 5,
            ],
            [
                'id' => 'cover-image',
                'type' => 'cover',
                'sort_order' => 30,
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('images.0.id', 'thumb-image')
        ->assertJsonPath('images.0.sort_order', 5)
        ->assertJsonPath('images.1.id', 'cover-image')
        ->assertJsonPath('images.1.sort_order', 30);

    $brand->refresh();

    expect(data_get($brand->images, '0.id'))->toBe('thumb-image')
        ->and(data_get($brand->images, '0.sort_order'))->toBe(5)
        ->and(data_get($brand->images, '1.id'))->toBe('cover-image')
        ->and(data_get($brand->images, '1.sort_order'))->toBe(30);
});

it('includes products count on brands when requested', function () {
    $brand = Brand::factory()->create(['active' => true]);
    Product::factory()
        ->count(2)
        ->create([
            'brand_id' => $brand->getKey(),
            'active' => true,
            'visible_from' => null,
            'visible_until' => null,
        ]);

    getJson(config('venditio.routes.api.v1.prefix') . '/brands?all=1&include=products_count')
        ->assertOk()
        ->assertJsonPath('0.id', $brand->getKey())
        ->assertJsonPath('0.products_count', 2);
});

it('includes products count on brand show only when requested', function () {
    $brand = Brand::factory()->create(['active' => true]);
    Product::factory()
        ->count(3)
        ->create([
            'brand_id' => $brand->getKey(),
            'active' => true,
            'visible_from' => null,
            'visible_until' => null,
        ]);

    getJson(config('venditio.routes.api.v1.prefix') . "/brands/{$brand->getKey()}")
        ->assertOk()
        ->assertJsonMissingPath('products_count');

    getJson(config('venditio.routes.api.v1.prefix') . "/brands/{$brand->getKey()}?include=products_count")
        ->assertOk()
        ->assertJsonPath('products_count', 3);
});

it('rejects unsupported brand includes', function () {
    getJson(config('venditio.routes.api.v1.prefix') . '/brands?include=unknown')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['include.0']);
});
