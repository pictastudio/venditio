<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Models\{ProductType, ProductVariant, ProductVariantOption};

use function Pest\Laravel\{assertDatabaseHas, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

it('creates product types, variants, and options', function () {
    $typeResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_types', [
        'name' => 'Apparel',
        'active' => true,
    ])->assertCreated();

    $productTypeId = $typeResponse->json('id');

    $variantResponse = postJson(config('venditio.routes.api.v1.prefix') . '/product_variants', [
        'product_type_id' => $productTypeId,
        'name' => 'Color',
        'sort_order' => 1,
    ])->assertCreated();

    $variantId = $variantResponse->json('id');

    postJson(config('venditio.routes.api.v1.prefix') . '/product_variant_options', [
        'product_variant_id' => $variantId,
        'name' => 'red',
        'sort_order' => 1,
    ])->assertCreated()
        ->assertJsonFragment([
            'product_variant_id' => $variantId,
            'name' => 'red',
        ]);
});

it('assigns default product type when product_type_id is omitted while creating variants', function () {
    $defaultProductType = ProductType::factory()->create([
        'active' => false,
        'is_default' => true,
    ]);

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/product_variants', [
        'name' => 'Color',
        'sort_order' => 1,
    ])->assertCreated();

    assertDatabaseHas('product_variants', [
        'id' => $response->json('id'),
        'product_type_id' => $defaultProductType->getKey(),
    ]);
});

it('returns validation error when creating variants without product_type_id and no default exists', function () {
    postJson(config('venditio.routes.api.v1.prefix') . '/product_variants', [
        'name' => 'Color',
        'sort_order' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['product_type_id']);
});

it('filters variants by product type', function () {
    $productType = ProductType::factory()->create();
    $otherType = ProductType::factory()->create();

    ProductVariant::factory()->create([
        'product_type_id' => $productType->getKey(),
    ]);
    ProductVariant::factory()->create([
        'product_type_id' => $otherType->getKey(),
    ]);

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/product_variants?product_type_id=' . $productType->getKey())
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('orders variants by sort_order within each product type', function () {
    $firstType = ProductType::factory()->create();
    $secondType = ProductType::factory()->create();

    ProductVariant::factory()->create([
        'product_type_id' => $firstType->getKey(),
        'name' => 'First Type Late',
        'sort_order' => 20,
    ]);
    ProductVariant::factory()->create([
        'product_type_id' => $firstType->getKey(),
        'name' => 'First Type Early',
        'sort_order' => 10,
    ]);
    ProductVariant::factory()->create([
        'product_type_id' => $secondType->getKey(),
        'name' => 'Second Type Late',
        'sort_order' => 30,
    ]);
    ProductVariant::factory()->create([
        'product_type_id' => $secondType->getKey(),
        'name' => 'Second Type Early',
        'sort_order' => 5,
    ]);

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/product_variants?all=1')
        ->assertOk();

    expect(collect($response->json())
        ->map(fn (array $variant): string => $variant['product_type_id'] . ':' . $variant['name'])
        ->all())->toBe([
            $firstType->getKey() . ':First Type Early',
            $firstType->getKey() . ':First Type Late',
            $secondType->getKey() . ':Second Type Early',
            $secondType->getKey() . ':Second Type Late',
        ]);
});

it('filters variant options by variant', function () {
    $variant = ProductVariant::factory()->create();
    $otherVariant = ProductVariant::factory()->create();

    ProductVariantOption::factory()->create([
        'product_variant_id' => $variant->getKey(),
        'name' => 'red',
    ]);
    ProductVariantOption::factory()->create([
        'product_variant_id' => $otherVariant->getKey(),
        'name' => 'blue',
    ]);

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/product_variant_options?product_variant_id=' . $variant->getKey())
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('orders variant options by sort_order within each variant', function () {
    $firstVariant = ProductVariant::factory()->create();
    $secondVariant = ProductVariant::factory()->create();

    ProductVariantOption::factory()->create([
        'product_variant_id' => $firstVariant->getKey(),
        'name' => 'First Variant Late',
        'sort_order' => 20,
    ]);
    ProductVariantOption::factory()->create([
        'product_variant_id' => $firstVariant->getKey(),
        'name' => 'First Variant Early',
        'sort_order' => 10,
    ]);
    ProductVariantOption::factory()->create([
        'product_variant_id' => $secondVariant->getKey(),
        'name' => 'Second Variant Late',
        'sort_order' => 30,
    ]);
    ProductVariantOption::factory()->create([
        'product_variant_id' => $secondVariant->getKey(),
        'name' => 'Second Variant Early',
        'sort_order' => 5,
    ]);

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/product_variant_options?all=1')
        ->assertOk();

    expect(collect($response->json())
        ->map(fn (array $option): string => $option['product_variant_id'] . ':' . $option['name'])
        ->all())->toBe([
            $firstVariant->getKey() . ':First Variant Early',
            $firstVariant->getKey() . ':First Variant Late',
            $secondVariant->getKey() . ':Second Variant Early',
            $secondVariant->getKey() . ':Second Variant Late',
        ]);
});

it('rejects duplicate product variant option names for the same variant', function () {
    $variant = ProductVariant::factory()->create();

    postJson(config('venditio.routes.api.v1.prefix') . '/product_variant_options', [
        'product_variant_id' => $variant->getKey(),
        'name' => 'red',
        'sort_order' => 1,
    ])->assertCreated();

    postJson(config('venditio.routes.api.v1.prefix') . '/product_variant_options', [
        'product_variant_id' => $variant->getKey(),
        'name' => 'red',
        'sort_order' => 2,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('allows same product variant option name on different variants', function () {
    $firstVariant = ProductVariant::factory()->create();
    $secondVariant = ProductVariant::factory()->create();

    postJson(config('venditio.routes.api.v1.prefix') . '/product_variant_options', [
        'product_variant_id' => $firstVariant->getKey(),
        'name' => 'red',
        'sort_order' => 1,
    ])->assertCreated();

    postJson(config('venditio.routes.api.v1.prefix') . '/product_variant_options', [
        'product_variant_id' => $secondVariant->getKey(),
        'name' => 'red',
        'sort_order' => 1,
    ])->assertCreated();
});

it('rejects update when product variant option name duplicates within the same variant', function () {
    $variant = ProductVariant::factory()->create();
    $firstOption = ProductVariantOption::factory()->create([
        'product_variant_id' => $variant->getKey(),
        'name' => 'red',
    ]);
    $secondOption = ProductVariantOption::factory()->create([
        'product_variant_id' => $variant->getKey(),
        'name' => 'blue',
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/product_variant_options/{$secondOption->getKey()}", [
        'name' => $firstOption->name,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('rejects update when moving an option to a variant that already has the same name', function () {
    $firstVariant = ProductVariant::factory()->create();
    $secondVariant = ProductVariant::factory()->create();

    ProductVariantOption::factory()->create([
        'product_variant_id' => $secondVariant->getKey(),
        'name' => 'red',
    ]);

    $optionToMove = ProductVariantOption::factory()->create([
        'product_variant_id' => $firstVariant->getKey(),
        'name' => 'red',
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/product_variant_options/{$optionToMove->getKey()}", [
        'product_variant_id' => $secondVariant->getKey(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['product_variant_id']);
});

it('stores accept_hex_color on product variants', function () {
    $productType = ProductType::factory()->create();

    postJson(config('venditio.routes.api.v1.prefix') . '/product_variants', [
        'product_type_id' => $productType->getKey(),
        'name' => 'Color',
        'accept_hex_color' => true,
        'sort_order' => 1,
    ])->assertCreated()
        ->assertJsonFragment([
            'accept_hex_color' => true,
        ]);
});

it('rejects hex_color when variant does not accept it', function () {
    $variant = ProductVariant::factory()->create([
        'accept_hex_color' => false,
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . '/product_variant_options', [
        'product_variant_id' => $variant->getKey(),
        'name' => 'red',
        'hex_color' => '#ff0000',
        'sort_order' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['hex_color']);
});

it('allows hex_color when variant accepts it', function () {
    $variant = ProductVariant::factory()->create([
        'accept_hex_color' => true,
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . '/product_variant_options', [
        'product_variant_id' => $variant->getKey(),
        'name' => 'red',
        'hex_color' => '#ff0000',
        'sort_order' => 1,
    ])->assertCreated()
        ->assertJsonFragment([
            'hex_color' => '#ff0000',
        ]);
});
