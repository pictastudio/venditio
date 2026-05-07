<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PictaStudio\Venditio\Models\{Product, ProductCategory, Tag};

use function Pest\Laravel\{assertDatabaseHas, assertDatabaseMissing, deleteJson, getJson, patch, patchJson, post, postJson};

uses(RefreshDatabase::class);

it('creates a product category', function () {
    $payload = [
        'name' => 'Shoes',
        'active' => true,
        'sort_order' => 1,
    ];

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/product_categories', $payload)
        ->assertCreated()
        ->assertJsonFragment([
            'name' => 'Shoes',
            'active' => true,
            'sort_order' => 1,
        ]);

    $categoryId = $response->json('id');

    expect($categoryId)->not->toBeNull();
    assertDatabaseHas('product_categories', ['id' => $categoryId]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductCategory)->getMorphClass(),
        'translatable_id' => $categoryId,
        'locale' => app()->getLocale(),
        'attribute' => 'name',
        'value' => 'Shoes',
    ]);
});

it('serializes casted custom objects like path as strings', function () {
    $category = ProductCategory::factory()->create([
        'sort_order' => 1,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . "/product_categories/{$category->getKey()}")
        ->assertOk()
        ->assertJsonPath('path', (string) $category->getKey());
});

it('updates a product category', function () {
    $category = ProductCategory::factory()->create([
        'name' => 'Old Name',
        'metadata' => ['seo' => ['title' => 'Old title']],
        'sort_order' => 1,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/product_categories/{$category->getKey()}", [
        'name' => 'New Name',
        'metadata' => ['seo' => ['title' => 'New title']],
        'sort_order' => 2,
    ])->assertOk()
        ->assertJsonFragment([
            'name' => 'New Name',
            'metadata' => ['seo' => ['title' => 'New title']],
            'sort_order' => 2,
        ]);

    assertDatabaseHas('product_categories', [
        'id' => $category->getKey(),
        'metadata' => json_encode(['seo' => ['title' => 'New title']]),
        'sort_order' => 2,
    ]);
    assertDatabaseHas('translations', [
        'translatable_type' => (new ProductCategory)->getMorphClass(),
        'translatable_id' => $category->getKey(),
        'locale' => app()->getLocale(),
        'attribute' => 'name',
        'value' => 'New Name',
    ]);
});

it('stores a product category with tags', function () {
    $tag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/product_categories?include=tags', [
        'name' => 'Tagged category',
        'active' => true,
        'sort_order' => 1,
        'tag_ids' => [$tag->getKey()],
    ])->assertCreated()
        ->assertJsonPath('tags.0.id', $tag->getKey());

    assertDatabaseHas('taggables', [
        'tag_id' => $tag->getKey(),
        'taggable_type' => (new ProductCategory)->getMorphClass(),
        'taggable_id' => $response->json('id'),
    ]);
});

it('updates product category tags using sync semantics', function () {
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
    $category = ProductCategory::factory()->create([
        'active' => true,
        'sort_order' => 1,
    ]);

    $category->tags()->sync([$firstTag->getKey()]);

    patchJson(config('venditio.routes.api.v1.prefix') . '/product_categories/' . $category->getKey() . '?include=tags', [
        'tag_ids' => [$secondTag->getKey()],
    ])->assertOk()
        ->assertJsonPath('tags.0.id', $secondTag->getKey());

    assertDatabaseMissing('taggables', [
        'tag_id' => $firstTag->getKey(),
        'taggable_type' => $category->getMorphClass(),
        'taggable_id' => $category->getKey(),
    ]);
    assertDatabaseHas('taggables', [
        'tag_id' => $secondTag->getKey(),
        'taggable_type' => $category->getMorphClass(),
        'taggable_id' => $category->getKey(),
    ]);
});

it('filters product categories index by tags', function () {
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
    $categoryWithTagA = ProductCategory::factory()->create([
        'active' => true,
        'sort_order' => 1,
    ]);
    $categoryWithTagB = ProductCategory::factory()->create([
        'active' => true,
        'sort_order' => 2,
    ]);

    $categoryWithTagA->tags()->sync([$tagA->getKey()]);
    $categoryWithTagB->tags()->sync([$tagB->getKey()]);

    $response = getJson(
        config('venditio.routes.api.v1.prefix') . '/product_categories?all=1&tag_ids[]=' . $tagA->getKey()
    )->assertOk();

    $ids = collect($response->json())
        ->pluck('id')
        ->all();

    expect($ids)->toContain($categoryWithTagA->getKey())
        ->not->toContain($categoryWithTagB->getKey());
});

it('validates tag ids when storing a product category', function () {
    postJson(config('venditio.routes.api.v1.prefix') . '/product_categories', [
        'name' => 'Invalid tags category',
        'sort_order' => 1,
        'tag_ids' => [999999],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['tag_ids.0']);
});

it('includes tags relation on product categories api when requested', function () {
    $tag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $category = ProductCategory::factory()->create([
        'active' => true,
        'sort_order' => 1,
    ]);

    $category->tags()->sync([$tag->getKey()]);

    getJson(config('venditio.routes.api.v1.prefix') . '/product_categories/' . $category->getKey() . '?include=tags')
        ->assertOk()
        ->assertJsonPath('tags.0.id', $tag->getKey());
});

it('rejects deleting a product category with connected products unless forced', function () {
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $category->products()->sync([$product->getKey()]);

    deleteJson(config('venditio.routes.api.v1.prefix') . '/product_categories/' . $category->getKey())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['products']);

    assertDatabaseHas('product_category_product', [
        'product_category_id' => $category->getKey(),
        'product_id' => $product->getKey(),
    ]);
});

it('force deletes a product category and clears related pivots', function () {
    $category = ProductCategory::factory()->create([
        'sort_order' => 1,
    ]);
    $child = ProductCategory::factory()->create([
        'parent_id' => $category->getKey(),
        'sort_order' => 2,
    ]);
    $grandchild = ProductCategory::factory()->create([
        'parent_id' => $child->getKey(),
        'sort_order' => 3,
    ]);
    $product = Product::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $tag = Tag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $category->products()->sync([$product->getKey()]);
    $category->tags()->sync([$tag->getKey()]);

    deleteJson(config('venditio.routes.api.v1.prefix') . '/product_categories/' . $category->getKey() . '?force=1')
        ->assertNoContent();

    expect(ProductCategory::withTrashed()->find($category->getKey())?->trashed())->toBeTrue();

    assertDatabaseMissing('product_category_product', [
        'product_category_id' => $category->getKey(),
        'product_id' => $product->getKey(),
    ]);
    assertDatabaseMissing('taggables', [
        'tag_id' => $tag->getKey(),
        'taggable_type' => $category->getMorphClass(),
        'taggable_id' => $category->getKey(),
    ]);
    assertDatabaseHas('product_categories', [
        'id' => $child->getKey(),
        'parent_id' => null,
    ]);

    $child->refresh();
    $grandchild->refresh();

    expect((string) $child->path)->toBe((string) $child->getKey())
        ->and((string) $grandchild->path)->toBe($child->getKey() . '.' . $grandchild->getKey());

    getJson(config('venditio.routes.api.v1.prefix') . '/product_categories?as_tree=1')
        ->assertOk()
        ->assertJsonPath('0.id', $child->getKey())
        ->assertJsonPath('0.children.0.id', $grandchild->getKey());
});

it('returns product categories as a tree when as_tree is true', function () {
    $root = ProductCategory::factory()->create([
        'name' => 'Root',
        'sort_order' => 1,
    ]);

    $child = ProductCategory::factory()->create([
        'name' => 'Child',
        'parent_id' => $root->getKey(),
        'sort_order' => 2,
    ]);

    ProductCategory::factory()->create([
        'name' => 'Other Root',
        'sort_order' => 3,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . '/product_categories?as_tree=1')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.name', 'Root')
        ->assertJsonPath('0.children.0.name', 'Child')
        ->assertJsonPath('1.name', 'Other Root');

    expect($child->fresh()->path)->not->toBeNull();
});

it('orders product categories by sort_order within each tree branch', function () {
    $rootA = ProductCategory::factory()->create([
        'name' => 'Root A',
        'sort_order' => 20,
    ]);

    $rootB = ProductCategory::factory()->create([
        'name' => 'Root B',
        'sort_order' => 10,
    ]);

    ProductCategory::factory()->create([
        'name' => 'Root A Child Late',
        'parent_id' => $rootA->getKey(),
        'sort_order' => 30,
    ]);

    ProductCategory::factory()->create([
        'name' => 'Root A Child Early',
        'parent_id' => $rootA->getKey(),
        'sort_order' => 5,
    ]);

    ProductCategory::factory()->create([
        'name' => 'Root B Child Late',
        'parent_id' => $rootB->getKey(),
        'sort_order' => 40,
    ]);

    ProductCategory::factory()->create([
        'name' => 'Root B Child Early',
        'parent_id' => $rootB->getKey(),
        'sort_order' => 1,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . '/product_categories?as_tree=1')
        ->assertOk()
        ->assertJsonPath('0.name', 'Root B')
        ->assertJsonPath('1.name', 'Root A')
        ->assertJsonPath('0.children.0.name', 'Root B Child Early')
        ->assertJsonPath('0.children.1.name', 'Root B Child Late')
        ->assertJsonPath('1.children.0.name', 'Root A Child Early')
        ->assertJsonPath('1.children.1.name', 'Root A Child Late');
});

it('returns root product categories ordered by sort_order on the index', function () {
    ProductCategory::factory()->create([
        'name' => 'Category Late',
        'sort_order' => 30,
    ]);

    ProductCategory::factory()->create([
        'name' => 'Category Early',
        'sort_order' => 10,
    ]);

    ProductCategory::factory()->create([
        'name' => 'Category Middle',
        'sort_order' => 20,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . '/product_categories?all=1')
        ->assertOk()
        ->assertJsonPath('0.name', 'Category Early')
        ->assertJsonPath('1.name', 'Category Middle')
        ->assertJsonPath('2.name', 'Category Late');
});

it('updates multiple product categories in one request', function () {
    $root = ProductCategory::factory()->create([
        'sort_order' => 1,
    ]);

    $firstCategory = ProductCategory::factory()->create([
        'sort_order' => 2,
    ]);

    $secondCategory = ProductCategory::factory()->create([
        'sort_order' => 3,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . '/product_categories/bulk/update', [
        'categories' => [
            [
                'id' => $firstCategory->getKey(),
                'parent_id' => $root->getKey(),
                'sort_order' => 10,
            ],
            [
                'id' => $secondCategory->getKey(),
                'parent_id' => null,
                'sort_order' => 20,
            ],
        ],
    ])->assertOk()
        ->assertJsonFragment([
            'id' => $firstCategory->getKey(),
            'parent_id' => $root->getKey(),
            'sort_order' => 10,
        ])
        ->assertJsonFragment([
            'id' => $secondCategory->getKey(),
            'parent_id' => null,
            'sort_order' => 20,
        ]);

    assertDatabaseHas('product_categories', [
        'id' => $firstCategory->getKey(),
        'parent_id' => $root->getKey(),
        'sort_order' => 10,
    ]);

    assertDatabaseHas('product_categories', [
        'id' => $secondCategory->getKey(),
        'parent_id' => null,
        'sort_order' => 20,
    ]);
});

it('applies bulk-updated category sort_order when rebuilding the tree', function () {
    $root = ProductCategory::factory()->create([
        'name' => 'Root',
        'sort_order' => 1,
    ]);

    $firstChild = ProductCategory::factory()->create([
        'name' => 'First Child',
        'parent_id' => $root->getKey(),
        'sort_order' => 10,
    ]);

    $secondChild = ProductCategory::factory()->create([
        'name' => 'Second Child',
        'parent_id' => $root->getKey(),
        'sort_order' => 20,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . '/product_categories/bulk/update', [
        'categories' => [
            [
                'id' => $firstChild->getKey(),
                'parent_id' => $root->getKey(),
                'sort_order' => 30,
            ],
            [
                'id' => $secondChild->getKey(),
                'parent_id' => $root->getKey(),
                'sort_order' => 5,
            ],
        ],
    ])->assertOk();

    getJson(config('venditio.routes.api.v1.prefix') . '/product_categories?as_tree=1')
        ->assertOk()
        ->assertJsonPath('0.name', 'Root')
        ->assertJsonPath('0.children.0.name', 'Second Child')
        ->assertJsonPath('0.children.1.name', 'First Child');
});

it('validates parent_id in bulk product category updates', function () {
    $category = ProductCategory::factory()->create([
        'sort_order' => 1,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . '/product_categories/bulk/update', [
        'categories' => [
            [
                'id' => $category->getKey(),
                'parent_id' => 999999,
                'sort_order' => 2,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['categories.0.parent_id']);
});

it('prevents circular references in bulk product category updates', function () {
    $firstCategory = ProductCategory::factory()->create([
        'sort_order' => 1,
    ]);

    $secondCategory = ProductCategory::factory()->create([
        'parent_id' => $firstCategory->getKey(),
        'sort_order' => 2,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . '/product_categories/bulk/update', [
        'categories' => [
            [
                'id' => $firstCategory->getKey(),
                'parent_id' => $secondCategory->getKey(),
                'sort_order' => 10,
            ],
            [
                'id' => $secondCategory->getKey(),
                'parent_id' => $firstCategory->getKey(),
                'sort_order' => 20,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['categories.0.parent_id', 'categories.1.parent_id']);
});

it('stores and exposes additional catalog fields on product categories', function () {
    $response = postJson(config('venditio.routes.api.v1.prefix') . '/product_categories', [
        'name' => 'Accessories',
        'abstract' => 'Category abstract',
        'description' => 'Category description',
        'metadata' => ['seo' => ['title' => 'Accessories']],
        'show_in_menu' => true,
        'in_evidence' => true,
        'visible_from' => now()->subDay()->toDateTimeString(),
        'visible_until' => now()->addDay()->toDateTimeString(),
        'sort_order' => 1,
    ])->assertCreated();

    $categoryId = $response->json('id');

    assertDatabaseHas('product_categories', [
        'id' => $categoryId,
        'show_in_menu' => true,
        'in_evidence' => true,
    ]);
});

it('uploads category images as a typed images collection on update', function () {
    Storage::fake('public');

    $category = ProductCategory::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $uploadDatePath = now()->format('Y/m/d');

    patch(
        config('venditio.routes.api.v1.prefix') . '/product_categories/' . $category->getKey(),
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

    $category->refresh();

    $thumb = collect($category->images)->firstWhere('type', 'thumb');
    $cover = collect($category->images)->firstWhere('type', 'cover');

    expect($category->images)->toBeArray()->toHaveCount(2)
        ->and(data_get($thumb, 'type'))->toBe('thumb')
        ->and(data_get($cover, 'type'))->toBe('cover')
        ->and(str_starts_with((string) data_get($thumb, 'src'), 'product_categories/' . $category->getKey() . '/thumb/' . $uploadDatePath . '/'))
        ->toBeTrue()
        ->and(str_starts_with((string) data_get($cover, 'src'), 'product_categories/' . $category->getKey() . '/cover/' . $uploadDatePath . '/'))
        ->toBeTrue();

    Storage::disk('public')->assertExists((string) data_get($thumb, 'src'));
    Storage::disk('public')->assertExists((string) data_get($cover, 'src'));
});

it('stores product category images with sort_order and returns them in the persisted order', function () {
    Storage::fake('public');

    $response = post(
        config('venditio.routes.api.v1.prefix') . '/product_categories',
        [
            'name' => 'Ordered Category',
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

    $category = ProductCategory::query()->findOrFail($response->json('id'));

    expect(data_get($category->images, '0.type'))->toBe('cover')
        ->and(data_get($category->images, '0.sort_order'))->toBe(10)
        ->and(data_get($category->images, '1.type'))->toBe('thumb')
        ->and(data_get($category->images, '1.sort_order'))->toBe(20);
});

it('allows multiple product category images with null type', function () {
    Storage::fake('public');

    $category = ProductCategory::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $uploadDatePath = now()->format('Y/m/d');

    patch(
        config('venditio.routes.api.v1.prefix') . '/product_categories/' . $category->getKey(),
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

    $category->refresh();

    expect($category->images)->toHaveCount(2)
        ->and(str_starts_with((string) data_get($category->images, '0.src'), 'product_categories/' . $category->getKey() . '/images/' . $uploadDatePath . '/'))->toBeTrue()
        ->and(str_starts_with((string) data_get($category->images, '1.src'), 'product_categories/' . $category->getKey() . '/images/' . $uploadDatePath . '/'))->toBeTrue();
});

it('updates product category image sort_order without requiring a new upload', function () {
    $category = ProductCategory::factory()->create([
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
                'src' => 'product_categories/thumb.jpg',
            ],
            [
                'id' => 'cover-image',
                'type' => 'cover',
                'alt' => 'Cover',
                'mimetype' => 'image/jpeg',
                'sort_order' => 10,
                'src' => 'product_categories/cover.jpg',
            ],
        ],
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/product_categories/{$category->getKey()}", [
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

    $category->refresh();

    expect(data_get($category->images, '0.id'))->toBe('thumb-image')
        ->and(data_get($category->images, '0.sort_order'))->toBe(5)
        ->and(data_get($category->images, '1.id'))->toBe('cover-image')
        ->and(data_get($category->images, '1.sort_order'))->toBe(30);
});

it('includes products count on product categories when requested', function () {
    $category = ProductCategory::factory()->create([
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

    $category->products()->sync($products->map->getKey()->all());

    getJson(config('venditio.routes.api.v1.prefix') . '/product_categories?all=1&include=products_count')
        ->assertOk()
        ->assertJsonPath('0.id', $category->getKey())
        ->assertJsonPath('0.products_count', 2);
});

it('includes products count on product category show only when requested', function () {
    $category = ProductCategory::factory()->create([
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

    $category->products()->sync($products->map->getKey()->all());

    getJson(config('venditio.routes.api.v1.prefix') . "/product_categories/{$category->getKey()}")
        ->assertOk()
        ->assertJsonMissingPath('products_count');

    getJson(config('venditio.routes.api.v1.prefix') . "/product_categories/{$category->getKey()}?include=products_count")
        ->assertOk()
        ->assertJsonPath('products_count', 3);
});
