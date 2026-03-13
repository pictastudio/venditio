<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PictaStudio\Venditio\Models\Brand;

use function Pest\Laravel\{assertDatabaseHas, patch, postJson};

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

it('uploads brand thumb and cover images on update', function () {
    Storage::fake('public');

    $brand = Brand::factory()->create([
        'active' => true,
    ]);

    patch(
        config('venditio.routes.api.v1.prefix') . '/brands/' . $brand->getKey(),
        [
            'img_thumb' => [
                'file' => UploadedFile::fake()->image('thumb.jpg'),
                'alt' => 'thumb',
                'name' => 'Thumb',
            ],
            'img_cover' => [
                'file' => UploadedFile::fake()->image('cover.jpg'),
                'alt' => 'cover',
                'name' => 'Cover',
            ],
        ],
        ['Accept' => 'application/json']
    )->assertOk();

    $brand->refresh();

    expect(str_starts_with((string) data_get($brand->img_thumb, 'src'), 'brands/' . $brand->getKey() . '/img_thumb/'))
        ->toBeTrue()
        ->and(str_starts_with((string) data_get($brand->img_cover, 'src'), 'brands/' . $brand->getKey() . '/img_cover/'))
        ->toBeTrue();

    Storage::disk('public')->assertExists((string) data_get($brand->img_thumb, 'src'));
    Storage::disk('public')->assertExists((string) data_get($brand->img_cover, 'src'));
});
