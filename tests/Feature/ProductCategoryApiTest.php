<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PictaStudio\Venditio\Models\{ProductCategory, Tag};

use function Pest\Laravel\{assertDatabaseHas, assertDatabaseMissing, getJson, patch, patchJson, postJson};

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
        'sort_order' => 1,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/product_categories/{$category->getKey()}", [
        'name' => 'New Name',
        'sort_order' => 2,
    ])->assertOk()
        ->assertJsonFragment([
            'name' => 'New Name',
            'sort_order' => 2,
        ]);

    assertDatabaseHas('product_categories', [
        'id' => $category->getKey(),
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

it('uploads category thumb and cover images on update', function () {
    Storage::fake('public');

    $category = ProductCategory::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    patch(
        config('venditio.routes.api.v1.prefix') . '/product_categories/' . $category->getKey(),
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

    $category->refresh();

    expect(str_starts_with((string) data_get($category->img_thumb, 'src'), 'product_categories/' . $category->getKey() . '/img_thumb/'))
        ->toBeTrue()
        ->and(str_starts_with((string) data_get($category->img_cover, 'src'), 'product_categories/' . $category->getKey() . '/img_cover/'))
        ->toBeTrue();

    Storage::disk('public')->assertExists((string) data_get($category->img_thumb, 'src'));
    Storage::disk('public')->assertExists((string) data_get($category->img_cover, 'src'));
});
