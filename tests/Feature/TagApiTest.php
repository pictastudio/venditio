<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Brand, Product, Tag, ProductType, TaxClass};

use function Pest\Laravel\{assertDatabaseHas, getJson, patchJson, postJson};

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

it('associates tags polymorphically to products, brands, and tags', function () {
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

    patchJson(config('venditio.routes.api.v1.prefix') . '/products/' . $product->getKey(), [
        'tag_ids' => [$tag->getKey()],
    ])->assertOk();

    patchJson(config('venditio.routes.api.v1.prefix') . '/brands/' . $brand->getKey(), [
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
        'taggable_type' => $taggableTag->getMorphClass(),
        'taggable_id' => $taggableTag->getKey(),
    ]);
});
