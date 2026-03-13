<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Models\{Currency, Inventory, Product};

use function Pest\Laravel\{assertDatabaseHas, patchJson, postJson};

uses(RefreshDatabase::class);

it('stores inventory reorder fields', function () {
    $product = Product::factory()->create();
    $product->inventory()->delete();
    $currency = Currency::factory()->create();

    postJson(config('venditio.routes.api.v1.prefix') . '/inventories', [
        'product_id' => $product->getKey(),
        'currency_id' => $currency->getKey(),
        'stock' => 20,
        'minimum_reorder_quantity' => 12,
        'reorder_lead_days' => 5,
        'price' => 49.99,
    ])->assertCreated()
        ->assertJsonPath('minimum_reorder_quantity', 12)
        ->assertJsonPath('reorder_lead_days', 5);

    assertDatabaseHas('inventories', [
        'product_id' => $product->getKey(),
        'currency_id' => $currency->getKey(),
        'minimum_reorder_quantity' => 12,
        'reorder_lead_days' => 5,
    ]);
});

it('updates inventory reorder fields', function () {
    $inventory = Inventory::factory()->for(Product::factory())->for(Currency::factory())->create([
        'minimum_reorder_quantity' => null,
        'reorder_lead_days' => null,
        'price' => 12.50,
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/inventories/{$inventory->getKey()}", [
        'minimum_reorder_quantity' => 15,
        'reorder_lead_days' => 9,
    ])->assertOk()
        ->assertJsonPath('minimum_reorder_quantity', 15)
        ->assertJsonPath('reorder_lead_days', 9);

    assertDatabaseHas('inventories', [
        'id' => $inventory->getKey(),
        'minimum_reorder_quantity' => 15,
        'reorder_lead_days' => 9,
    ]);
});
