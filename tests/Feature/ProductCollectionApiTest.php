<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PictaStudio\Venditio\Enums\{DiscountType, ProductStatus};
use PictaStudio\Venditio\Models\{Product, ProductCollection, Tag};

use function Pest\Laravel\{assertDatabaseHas, assertDatabaseMissing, deleteJson, getJson, patch, patchJson, post, postJson};

uses(RefreshDatabase::class);

it('creates a product collection', function () {
    $response = postJson(config('venditio.routes.api.v1.prefix') . '/product_collections', [
        'name' => 'Summer Picks',
        'description' => 'Collection description',
        'metadata' => ['seo' => ['title' => 'Summer Picks']],
        'active' => true,
        'visible_from' => now()->subDay()->toDateTimeString(),
        'visible_until' => now()->addDay()->toDateTimeString(),
    ])->assertCreated()
        ->assertJsonFragment([
            'name' => 'Summer Picks',
            'slug' => 'summer-picks',
            'description' => 'Collection description',
            'metadata' => ['seo' => ['title' => 'Summer Picks']],
            'active' => true,
        ]);

    $collectionId = $response->json('id');

    assertDatabaseHas('product_collections', [
        'id' => $collectionId,
        'metadata' => json_encode(['seo' => ['title' => 'Summer Picks']]),
        'active' => true,
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductCollection)->getMorphClass(),
        'translatable_id' => $collectionId,
        'locale' => app()->getLocale(),
        'attribute' => 'name',
        'value' => 'Summer Picks',
    ]);
});

it('updates a product collection', function () {
    $collection = ProductCollection::factory()->create([
        'name' => 'Old Collection',
        'metadata' => ['seo' => ['title' => 'Old title']],
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/product_collections/{$collection->getKey()}", [
        'name' => 'Updated Collection',
        'description' => 'Updated description',
        'metadata' => ['seo' => ['title' => 'Updated title']],
        'active' => false,
    ])->assertOk()
        ->assertJsonFragment([
            'name' => 'Updated Collection',
            'description' => 'Updated description',
            'metadata' => ['seo' => ['title' => 'Updated title']],
            'active' => false,
        ]);

    assertDatabaseHas('product_collections', [
        'id' => $collection->getKey(),
        'metadata' => json_encode(['seo' => ['title' => 'Updated title']]),
        'active' => false,
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductCollection)->getMorphClass(),
        'translatable_id' => $collection->getKey(),
        'locale' => app()->getLocale(),
        'attribute' => 'name',
        'value' => 'Updated Collection',
    ]);
});

it('stores a product collection with tags', function () {
    $tag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/product_collections?include=tags', [
        'name' => 'Tagged collection',
        'active' => true,
        'tag_ids' => [$tag->getKey()],
    ])->assertCreated()
        ->assertJsonPath('tags.0.id', $tag->getKey());

    assertDatabaseHas('taggables', [
        'tag_id' => $tag->getKey(),
        'taggable_type' => (new ProductCollection)->getMorphClass(),
        'taggable_id' => $response->json('id'),
    ]);
});

it('updates product collection tags using sync semantics', function () {
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
    $collection = ProductCollection::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $collection->tags()->sync([$firstTag->getKey()]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/product_collections/{$collection->getKey()}?include=tags", [
        'tag_ids' => [$secondTag->getKey()],
    ])->assertOk()
        ->assertJsonPath('tags.0.id', $secondTag->getKey());

    assertDatabaseMissing('taggables', [
        'tag_id' => $firstTag->getKey(),
        'taggable_type' => $collection->getMorphClass(),
        'taggable_id' => $collection->getKey(),
    ]);
    assertDatabaseHas('taggables', [
        'tag_id' => $secondTag->getKey(),
        'taggable_type' => $collection->getMorphClass(),
        'taggable_id' => $collection->getKey(),
    ]);
});

it('includes products and discounts on product collections api when requested', function () {
    $collection = ProductCollection::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $product = Product::factory()->create([
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $collection->products()->sync([$product->getKey()]);
    $collection->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'name' => 'Collection 10%',
        'code' => 'COL10',
        'active' => true,
        'starts_at' => now()->subMinute(),
        'ends_at' => now()->addDay(),
        'apply_to_cart_total' => false,
        'apply_once_per_cart' => false,
        'max_uses_per_user' => null,
        'one_per_user' => false,
        'free_shipping' => false,
        'minimum_order_total' => null,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . "/product_collections/{$collection->getKey()}?include=products,discounts")
        ->assertOk()
        ->assertJsonPath('products.0.id', $product->getKey())
        ->assertJsonPath('discounts.0.code', 'COL10');
});

it('uploads product collection images as a typed images collection on update', function () {
    Storage::fake('public');

    $collection = ProductCollection::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    patch(
        config('venditio.routes.api.v1.prefix') . '/product_collections/' . $collection->getKey(),
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

    $collection->refresh();

    $thumb = collect($collection->images)->firstWhere('type', 'thumb');
    $cover = collect($collection->images)->firstWhere('type', 'cover');

    expect($collection->images)->toBeArray()->toHaveCount(2)
        ->and(data_get($thumb, 'type'))->toBe('thumb')
        ->and(data_get($cover, 'type'))->toBe('cover')
        ->and(str_starts_with((string) data_get($thumb, 'src'), 'product_collections/' . $collection->getKey() . '/thumb/'))
        ->toBeTrue()
        ->and(str_starts_with((string) data_get($cover, 'src'), 'product_collections/' . $collection->getKey() . '/cover/'))
        ->toBeTrue();

    Storage::disk('public')->assertExists((string) data_get($thumb, 'src'));
    Storage::disk('public')->assertExists((string) data_get($cover, 'src'));
});

it('stores product collection images with sort_order and returns them in the persisted order', function () {
    Storage::fake('public');

    $response = post(
        config('venditio.routes.api.v1.prefix') . '/product_collections',
        [
            'name' => 'Ordered Collection',
            'active' => true,
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

    $collection = ProductCollection::query()->findOrFail($response->json('id'));

    expect(data_get($collection->images, '0.type'))->toBe('cover')
        ->and(data_get($collection->images, '0.sort_order'))->toBe(10)
        ->and(data_get($collection->images, '1.type'))->toBe('thumb')
        ->and(data_get($collection->images, '1.sort_order'))->toBe(20);
});

it('allows multiple product collection images with null type', function () {
    Storage::fake('public');

    $collection = ProductCollection::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    patch(
        config('venditio.routes.api.v1.prefix') . '/product_collections/' . $collection->getKey(),
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

    $collection->refresh();

    expect($collection->images)->toHaveCount(2)
        ->and(str_starts_with((string) data_get($collection->images, '0.src'), 'product_collections/' . $collection->getKey() . '/images/'))->toBeTrue()
        ->and(str_starts_with((string) data_get($collection->images, '1.src'), 'product_collections/' . $collection->getKey() . '/images/'))->toBeTrue();
});

it('updates product collection image sort_order without requiring a new upload', function () {
    $collection = ProductCollection::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
        'images' => [
            [
                'id' => 'thumb-image',
                'type' => 'thumb',
                'alt' => 'Thumb',
                'mimetype' => 'image/jpeg',
                'sort_order' => 20,
                'src' => 'product_collections/thumb.jpg',
            ],
            [
                'id' => 'cover-image',
                'type' => 'cover',
                'alt' => 'Cover',
                'mimetype' => 'image/jpeg',
                'sort_order' => 10,
                'src' => 'product_collections/cover.jpg',
            ],
        ],
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/product_collections/{$collection->getKey()}", [
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

    $collection->refresh();

    expect(data_get($collection->images, '0.id'))->toBe('thumb-image')
        ->and(data_get($collection->images, '0.sort_order'))->toBe(5)
        ->and(data_get($collection->images, '1.id'))->toBe('cover-image')
        ->and(data_get($collection->images, '1.sort_order'))->toBe(30);
});

it('deletes a product collection', function () {
    $collection = ProductCollection::factory()->create();

    deleteJson(config('venditio.routes.api.v1.prefix') . '/product_collections/' . $collection->getKey())
        ->assertNoContent();

    expect(ProductCollection::withTrashed()->find($collection->getKey())?->trashed())->toBeTrue();
});

it('includes products count on product collections when requested', function () {
    $collection = ProductCollection::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $products = Product::factory()
        ->count(2)
        ->create([
            'active' => true,
            'visible_from' => null,
            'visible_until' => null,
        ]);

    $collection->products()->sync($products->map->getKey()->all());

    getJson(config('venditio.routes.api.v1.prefix') . '/product_collections?all=1&include=products_count')
        ->assertOk()
        ->assertJsonPath('0.id', $collection->getKey())
        ->assertJsonPath('0.products_count', 2);
});

it('includes products count on product collection show only when requested', function () {
    $collection = ProductCollection::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $products = Product::factory()
        ->count(3)
        ->create([
            'active' => true,
            'visible_from' => null,
            'visible_until' => null,
        ]);

    $collection->products()->sync($products->map->getKey()->all());

    getJson(config('venditio.routes.api.v1.prefix') . "/product_collections/{$collection->getKey()}")
        ->assertOk()
        ->assertJsonMissingPath('products_count');

    getJson(config('venditio.routes.api.v1.prefix') . "/product_collections/{$collection->getKey()}?include=products_count")
        ->assertOk()
        ->assertJsonPath('products_count', 3);
});
