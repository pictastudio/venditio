<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Models\ProductType;

use function Pest\Laravel\{assertDatabaseHas, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

it('creates and updates a product custom field', function () {
    $productType = ProductType::factory()->create();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/product_custom_fields', [
        'product_type_id' => $productType->getKey(),
        'name' => 'Material',
        'required' => true,
        'sort_order' => 10,
        'type' => 'text',
        'options' => ['group' => 'details'],
    ])->assertCreated()
        ->assertJsonFragment([
            'name' => 'Material',
            'required' => true,
            'sort_order' => 10,
            'type' => 'text',
        ]);

    $fieldId = $response->json('id');

    patchJson(config('venditio.routes.api.v1.prefix') . "/product_custom_fields/{$fieldId}", [
        'sort_order' => 5,
        'required' => false,
    ])->assertOk()
        ->assertJsonFragment([
            'id' => $fieldId,
            'sort_order' => 5,
            'required' => false,
        ]);

    assertDatabaseHas('product_custom_fields', [
        'id' => $fieldId,
        'product_type_id' => $productType->getKey(),
        'sort_order' => 5,
        'required' => false,
    ]);
});

it('orders product custom fields by sort_order within each product type', function () {
    $firstType = ProductType::factory()->create();
    $secondType = ProductType::factory()->create();

    postJson(config('venditio.routes.api.v1.prefix') . '/product_custom_fields', [
        'product_type_id' => $firstType->getKey(),
        'name' => 'First Type Late',
        'sort_order' => 20,
        'type' => 'text',
    ])->assertCreated();

    postJson(config('venditio.routes.api.v1.prefix') . '/product_custom_fields', [
        'product_type_id' => $firstType->getKey(),
        'name' => 'First Type Early',
        'sort_order' => 10,
        'type' => 'text',
    ])->assertCreated();

    postJson(config('venditio.routes.api.v1.prefix') . '/product_custom_fields', [
        'product_type_id' => $secondType->getKey(),
        'name' => 'Second Type Late',
        'sort_order' => 30,
        'type' => 'text',
    ])->assertCreated();

    postJson(config('venditio.routes.api.v1.prefix') . '/product_custom_fields', [
        'product_type_id' => $secondType->getKey(),
        'name' => 'Second Type Early',
        'sort_order' => 5,
        'type' => 'text',
    ])->assertCreated();

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/product_custom_fields?all=1')
        ->assertOk();

    expect(collect($response->json())
        ->map(fn (array $field): string => $field['product_type_id'] . ':' . $field['name'])
        ->all())->toBe([
            $firstType->getKey() . ':First Type Early',
            $firstType->getKey() . ':First Type Late',
            $secondType->getKey() . ':Second Type Early',
            $secondType->getKey() . ':Second Type Late',
        ]);
});
