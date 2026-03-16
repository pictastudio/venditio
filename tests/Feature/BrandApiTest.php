<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PictaStudio\Venditio\Models\Brand;

use function Pest\Laravel\{assertDatabaseHas, patch, patchJson, post, postJson};

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
