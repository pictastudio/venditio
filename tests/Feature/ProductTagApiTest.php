<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Brand, Product, ProductTag, ProductType, TaxClass};

use function Pest\Laravel\{assertDatabaseHas, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

it('creates product tags and supports product_type include and filter', function () {
    $productType = ProductType::factory()->create(['active' => true]);
    $otherProductType = ProductType::factory()->create(['active' => true]);

    postJson(config('venditio.routes.api.v1.prefix') . '/product_tags', [
        'product_type_id' => $productType->getKey(),
        'name' => 'Summer',
        'sort_order' => 1,
    ])->assertCreated();

    postJson(config('venditio.routes.api.v1.prefix') . '/product_tags', [
        'product_type_id' => $otherProductType->getKey(),
        'name' => 'Winter',
        'sort_order' => 2,
    ])->assertCreated();

    $response = getJson(
        config('venditio.routes.api.v1.prefix')
        . '/product_tags?all=1&include=product_type&product_type_id=' . $productType->getKey()
    )->assertOk();

    expect($response->json())->toHaveCount(1)
        ->and(data_get($response->json(), '0.product_type.id'))->toBe($productType->getKey());
});

it('inherits parent product_type_id on child tag creation', function () {
    $productType = ProductType::factory()->create(['active' => true]);

    $parentResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_tags', [
        'product_type_id' => $productType->getKey(),
        'name' => 'Parent',
        'sort_order' => 1,
    ])->assertCreated();

    $parentId = $parentResponse->json('id');

    $childResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_tags', [
        'parent_id' => $parentId,
        'name' => 'Child',
        'sort_order' => 2,
    ])->assertCreated();

    assertDatabaseHas('product_tags', [
        'id' => $childResponse->json('id'),
        'product_type_id' => $productType->getKey(),
    ]);
});

it('propagates updated product_type_id from parent to children', function () {
    $initialType = ProductType::factory()->create(['active' => true]);
    $updatedType = ProductType::factory()->create(['active' => true]);

    $parent = ProductTag::factory()->create([
        'product_type_id' => $initialType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $child = ProductTag::factory()->create([
        'parent_id' => $parent->getKey(),
        'product_type_id' => $initialType->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . '/product_tags/' . $parent->getKey(), [
        'product_type_id' => $updatedType->getKey(),
    ])->assertOk();

    assertDatabaseHas('product_tags', [
        'id' => $child->getKey(),
        'product_type_id' => $updatedType->getKey(),
    ]);
});

it('associates tags polymorphically to products, brands, and tags', function () {
    $taxClass = TaxClass::factory()->create();

    $tag = ProductTag::factory()->create([
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $taggableTag = ProductTag::factory()->create([
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

    patchJson(config('venditio.routes.api.v1.prefix') . '/product_tags/' . $taggableTag->getKey(), [
        'tag_ids' => [$tag->getKey()],
    ])->assertOk();

    assertDatabaseHas('taggables', [
        'product_tag_id' => $tag->getKey(),
        'taggable_type' => $product->getMorphClass(),
        'taggable_id' => $product->getKey(),
    ]);

    assertDatabaseHas('taggables', [
        'product_tag_id' => $tag->getKey(),
        'taggable_type' => $brand->getMorphClass(),
        'taggable_id' => $brand->getKey(),
    ]);

    assertDatabaseHas('taggables', [
        'product_tag_id' => $tag->getKey(),
        'taggable_type' => $taggableTag->getMorphClass(),
        'taggable_id' => $taggableTag->getKey(),
    ]);
});
