<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Brand, Product, ProductCategory, ProductType, Tag, TaxClass};

use function Pest\Laravel\{assertDatabaseHas, getJson, patchJson, post, postJson};

uses(RefreshDatabase::class);

it('creates tags and supports product_type include and filter', function () {
    $productType = ProductType::factory()->create(['active' => true]);
    $otherProductType = ProductType::factory()->create(['active' => true]);

    postJson(config('venditio.routes.api.v1.prefix') . '/tags', [
        'product_type_id' => $productType->getKey(),
        'name' => 'Summer',
        'sort_order' => 1,
    ])->assertCreated();

    postJson(config('venditio.routes.api.v1.prefix') . '/tags', [
        'product_type_id' => $otherProductType->getKey(),
        'name' => 'Winter',
        'sort_order' => 2,
    ])->assertCreated();

    $response = getJson(
        config('venditio.routes.api.v1.prefix')
        . '/tags?all=1&include=product_type&product_type_id=' . $productType->getKey()
    )->assertOk();

    expect($response->json())->toHaveCount(1)
        ->and(data_get($response->json(), '0.product_type.id'))->toBe($productType->getKey());
});

it('stores tag images as a catalog images collection', function () {
    Storage::fake('public');

    $response = post(
        config('venditio.routes.api.v1.prefix') . '/tags',
        [
            'name' => 'Visual tag',
            'sort_order' => 1,
            'images' => [
                [
                    'file' => UploadedFile::fake()->image('thumb.jpg'),
                    'type' => 'thumb',
                    'alt' => 'Thumb',
                ],
                [
                    'file' => UploadedFile::fake()->image('gallery-a.jpg'),
                    'type' => null,
                    'alt' => 'Gallery A',
                    'sort_order' => 10,
                ],
                [
                    'file' => UploadedFile::fake()->image('gallery-b.jpg'),
                    'alt' => 'Gallery B',
                    'sort_order' => 20,
                ],
            ],
        ],
        ['Accept' => 'application/json']
    )->assertCreated()
        ->assertJsonCount(3, 'images')
        ->assertJsonPath('images.0.type', 'thumb')
        ->assertJsonPath('images.1.type', null)
        ->assertJsonPath('images.2.type', null);

    $tag = Tag::query()->findOrFail($response->json('id'));
    $thumb = collect($tag->images)->firstWhere('type', 'thumb');
    $genericImages = collect($tag->images)->where('type', null)->values();

    expect($tag->images)->toBeArray()->toHaveCount(3)
        ->and(str_starts_with((string) data_get($thumb, 'src'), 'tags/' . $tag->getKey() . '/thumb/'))->toBeTrue()
        ->and(str_starts_with((string) data_get($genericImages->first(), 'src'), 'tags/' . $tag->getKey() . '/images/'))->toBeTrue();

    Storage::disk('public')->assertExists((string) data_get($thumb, 'src'));
    Storage::disk('public')->assertExists((string) data_get($genericImages->first(), 'src'));
});

it('updates tag image metadata without requiring a new upload', function () {
    $tag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'images' => [
            [
                'id' => 'generic-image',
                'type' => null,
                'alt' => 'Old alt',
                'mimetype' => 'image/jpeg',
                'sort_order' => 10,
                'src' => 'tags/generic.jpg',
            ],
        ],
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . '/tags/' . $tag->getKey(), [
        'images' => [
            [
                'id' => 'generic-image',
                'alt' => 'Updated alt',
                'sort_order' => 2,
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('images.0.id', 'generic-image')
        ->assertJsonPath('images.0.type', null)
        ->assertJsonPath('images.0.alt', 'Updated alt')
        ->assertJsonPath('images.0.sort_order', 2);
});

it('rejects more than one thumb or cover image per tag payload', function () {
    Storage::fake('public');

    post(
        config('venditio.routes.api.v1.prefix') . '/tags',
        [
            'name' => 'Duplicate type tag',
            'sort_order' => 1,
            'images' => [
                [
                    'file' => UploadedFile::fake()->image('thumb-a.jpg'),
                    'type' => 'thumb',
                ],
                [
                    'file' => UploadedFile::fake()->image('thumb-b.jpg'),
                    'type' => 'thumb',
                ],
            ],
        ],
        ['Accept' => 'application/json']
    )->assertUnprocessable()
        ->assertJsonValidationErrors(['images.1.type']);
});

it('rejects moving a tag image to a typed slot already in use', function () {
    $tag = Tag::factory()->create([
        'images' => [
            [
                'id' => 'thumb-image',
                'type' => 'thumb',
                'src' => 'tags/thumb.jpg',
                'sort_order' => 0,
            ],
            [
                'id' => 'generic-image',
                'type' => null,
                'src' => 'tags/generic.jpg',
                'sort_order' => 1,
            ],
        ],
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . '/tags/' . $tag->getKey(), [
        'images' => [
            [
                'id' => 'generic-image',
                'type' => 'thumb',
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['images.0.type']);
});

it('inherits parent product_type_id on child tag creation', function () {
    $productType = ProductType::factory()->create(['active' => true]);

    $parentResponse = postJson(config('venditio.routes.api.v1.prefix') . '/tags', [
        'product_type_id' => $productType->getKey(),
        'name' => 'Parent',
        'sort_order' => 1,
    ])->assertCreated();

    $parentId = $parentResponse->json('id');

    $childResponse = postJson(config('venditio.routes.api.v1.prefix') . '/tags', [
        'parent_id' => $parentId,
        'name' => 'Child',
        'sort_order' => 2,
    ])->assertCreated();

    assertDatabaseHas('tags', [
        'id' => $childResponse->json('id'),
        'product_type_id' => $productType->getKey(),
    ]);
});

it('propagates updated product_type_id from parent to children', function () {
    $initialType = ProductType::factory()->create(['active' => true]);
    $updatedType = ProductType::factory()->create(['active' => true]);

    $parent = Tag::factory()->create([
        'product_type_id' => $initialType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $child = Tag::factory()->create([
        'parent_id' => $parent->getKey(),
        'product_type_id' => $initialType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . '/tags/' . $parent->getKey(), [
        'product_type_id' => $updatedType->getKey(),
    ])->assertOk();

    assertDatabaseHas('tags', [
        'id' => $child->getKey(),
        'product_type_id' => $updatedType->getKey(),
    ]);
});

it('associates tags polymorphically to products, brands, product categories, and tags', function () {
    $taxClass = TaxClass::factory()->create();

    $tag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $taggableTag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $product = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $brand = Brand::factory()->create();
    $productCategory = ProductCategory::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . '/products/' . $product->getKey(), [
        'tag_ids' => [$tag->getKey()],
    ])->assertOk();

    patchJson(config('venditio.routes.api.v1.prefix') . '/brands/' . $brand->getKey(), [
        'tag_ids' => [$tag->getKey()],
    ])->assertOk();

    patchJson(config('venditio.routes.api.v1.prefix') . '/product_categories/' . $productCategory->getKey(), [
        'tag_ids' => [$tag->getKey()],
    ])->assertOk();

    patchJson(config('venditio.routes.api.v1.prefix') . '/tags/' . $taggableTag->getKey(), [
        'tag_ids' => [$tag->getKey()],
    ])->assertOk();

    assertDatabaseHas('taggables', [
        'tag_id' => $tag->getKey(),
        'taggable_type' => $product->getMorphClass(),
        'taggable_id' => $product->getKey(),
    ]);

    assertDatabaseHas('taggables', [
        'tag_id' => $tag->getKey(),
        'taggable_type' => $brand->getMorphClass(),
        'taggable_id' => $brand->getKey(),
    ]);

    assertDatabaseHas('taggables', [
        'tag_id' => $tag->getKey(),
        'taggable_type' => $productCategory->getMorphClass(),
        'taggable_id' => $productCategory->getKey(),
    ]);

    assertDatabaseHas('taggables', [
        'tag_id' => $tag->getKey(),
        'taggable_type' => $taggableTag->getMorphClass(),
        'taggable_id' => $taggableTag->getKey(),
    ]);
});

it('includes tags relation on brands api when requested', function () {
    $tag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $brand = Brand::factory()->create();
    $brand->tags()->sync([$tag->getKey()]);

    getJson(config('venditio.routes.api.v1.prefix') . '/brands/' . $brand->getKey() . '?include=tags')
        ->assertOk()
        ->assertJsonPath('tags.0.id', $tag->getKey());
});

it('orders tags by sort_order within each tree branch', function () {
    $rootA = Tag::factory()->create([
        'name' => 'Root A',
        'sort_order' => 20,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $rootB = Tag::factory()->create([
        'name' => 'Root B',
        'sort_order' => 10,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    Tag::factory()->create([
        'name' => 'Root A Child Late',
        'parent_id' => $rootA->getKey(),
        'sort_order' => 30,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    Tag::factory()->create([
        'name' => 'Root A Child Early',
        'parent_id' => $rootA->getKey(),
        'sort_order' => 5,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    Tag::factory()->create([
        'name' => 'Root B Child Late',
        'parent_id' => $rootB->getKey(),
        'sort_order' => 40,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    Tag::factory()->create([
        'name' => 'Root B Child Early',
        'parent_id' => $rootB->getKey(),
        'sort_order' => 1,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . '/tags?as_tree=1')
        ->assertOk()
        ->assertJsonPath('0.name', 'Root B')
        ->assertJsonPath('1.name', 'Root A')
        ->assertJsonPath('0.children.0.name', 'Root B Child Early')
        ->assertJsonPath('0.children.1.name', 'Root B Child Late')
        ->assertJsonPath('1.children.0.name', 'Root A Child Early')
        ->assertJsonPath('1.children.1.name', 'Root A Child Late');
});
