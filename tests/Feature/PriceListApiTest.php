<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Contracts\ProductPriceResolverInterface;
use PictaStudio\Venditio\Models\{Country, CountryTaxClass, Currency, PriceList, PriceListPrice, Product, TaxClass};

use function Pest\Laravel\{assertDatabaseHas, assertDatabaseMissing, deleteJson, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

function pl_createProduct(float $inventoryPrice = 100): Product
{
    $taxClass = TaxClass::factory()->create();
    pl_setupTaxEnvironment($taxClass);

    $product = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);

    $product->inventory()->updateOrCreate([], [
        'stock' => 100,
        'stock_reserved' => 0,
        'stock_available' => 100,
        'stock_min' => 0,
        'price' => $inventoryPrice,
        'purchase_price' => 40,
        'price_includes_tax' => false,
    ]);

    return $product->refresh();
}

function pl_setupTaxEnvironment(TaxClass $taxClass): void
{
    $currencyId = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'EUR', 'exchange_rate' => 1, 'is_enabled' => true, 'is_default' => false]
    )->getKey();

    $country = Country::query()->create([
        'name' => 'Italy',
        'iso_2' => 'IT',
        'iso_3' => 'ITA',
        'phone_code' => '+39',
        'currency_id' => $currencyId,
        'flag_emoji' => 'it',
        'capital' => 'Rome',
        'native' => 'Italia',
    ]);

    CountryTaxClass::query()->create([
        'country_id' => $country->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'rate' => 22,
    ]);
}

it('returns 404 for price list endpoints when feature is disabled', function () {
    config()->set('venditio.price_lists.enabled', false);
    $prefix = config('venditio.routes.api.v1.prefix');

    getJson($prefix . '/price_lists')->assertNotFound();
    getJson($prefix . '/price_list_prices')->assertNotFound();
});

it('provides full crud for global price lists when feature is enabled', function () {
    config()->set('venditio.price_lists.enabled', true);
    $prefix = config('venditio.routes.api.v1.prefix');

    $created = postJson($prefix . '/price_lists', [
        'name' => 'B2B',
        'code' => 'B2B',
        'active' => true,
    ])->assertCreated();

    $priceListId = $created->json('id');

    assertDatabaseHas('price_lists', [
        'id' => $priceListId,
        'name' => 'B2B',
        'code' => 'B2B',
        'active' => true,
    ]);

    getJson($prefix . '/price_lists?all=1')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $priceListId,
            'name' => 'B2B',
        ]);

    patchJson($prefix . '/price_lists/' . $priceListId, [
        'name' => 'B2B Updated',
        'active' => false,
    ])->assertOk()
        ->assertJsonPath('name', 'B2B Updated')
        ->assertJsonPath('active', false);

    assertDatabaseHas('price_lists', [
        'id' => $priceListId,
        'name' => 'B2B Updated',
        'active' => false,
    ]);

    deleteJson($prefix . '/price_lists/' . $priceListId)->assertNoContent();

    assertDatabaseMissing('price_lists', [
        'id' => $priceListId,
        'deleted_at' => null,
    ]);
});

it('provides full crud for product prices attached to price lists', function () {
    config()->set('venditio.price_lists.enabled', true);
    $prefix = config('venditio.routes.api.v1.prefix');
    $product = pl_createProduct();
    $priceList = PriceList::factory()->create([
        'name' => 'Retail',
    ]);

    $created = postJson($prefix . '/price_list_prices', [
        'product_id' => $product->getKey(),
        'price_list_id' => $priceList->getKey(),
        'price' => 89.90,
        'purchase_price' => 55.10,
        'price_includes_tax' => true,
        'is_default' => true,
    ])->assertCreated();

    $priceListPriceId = $created->json('id');

    assertDatabaseHas('price_list_prices', [
        'id' => $priceListPriceId,
        'product_id' => $product->getKey(),
        'price_list_id' => $priceList->getKey(),
        'price' => 89.90,
        'is_default' => true,
    ]);

    getJson($prefix . '/price_list_prices?all=1&product_id=' . $product->getKey())
        ->assertOk()
        ->assertJsonFragment([
            'id' => $priceListPriceId,
            'product_id' => $product->getKey(),
            'price_list_id' => $priceList->getKey(),
        ]);

    patchJson($prefix . '/price_list_prices/' . $priceListPriceId, [
        'price' => 79.90,
    ])->assertOk()
        ->assertJsonPath('price', 79.9);

    assertDatabaseHas('price_list_prices', [
        'id' => $priceListPriceId,
        'price' => 79.90,
    ]);

    deleteJson($prefix . '/price_list_prices/' . $priceListPriceId)->assertNoContent();

    assertDatabaseMissing('price_list_prices', [
        'id' => $priceListPriceId,
        'deleted_at' => null,
    ]);
});

it('upserts multiple price list prices across multiple products and price lists', function () {
    config()->set('venditio.price_lists.enabled', true);
    $prefix = config('venditio.routes.api.v1.prefix');

    $firstProduct = pl_createProduct();
    $secondProduct = Product::factory()->create([
        'tax_class_id' => $firstProduct->tax_class_id,
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
    $secondProduct->inventory()->updateOrCreate([], [
        'stock' => 100,
        'stock_reserved' => 0,
        'stock_available' => 100,
        'stock_min' => 0,
        'price' => 110,
        'purchase_price' => 45,
        'price_includes_tax' => false,
    ]);
    $secondProduct = $secondProduct->refresh();
    $retail = PriceList::factory()->create(['name' => 'Retail']);
    $wholesale = PriceList::factory()->create(['name' => 'Wholesale']);

    $existingPrice = PriceListPrice::factory()->create([
        'product_id' => $firstProduct->getKey(),
        'price_list_id' => $retail->getKey(),
        'price' => 120,
        'is_default' => true,
    ]);

    postJson($prefix . '/price_list_prices/bulk/upsert', [
        'prices' => [
            [
                'product_id' => $firstProduct->getKey(),
                'price_list_id' => $retail->getKey(),
                'price' => 99.90,
                'is_default' => false,
            ],
            [
                'product_id' => $firstProduct->getKey(),
                'price_list_id' => $wholesale->getKey(),
                'price' => 89.90,
                'is_default' => true,
            ],
            [
                'product_id' => $secondProduct->getKey(),
                'price_list_id' => $retail->getKey(),
                'price' => 79.90,
                'is_default' => true,
            ],
            [
                'product_id' => $secondProduct->getKey(),
                'price_list_id' => $wholesale->getKey(),
                'price' => 69.90,
                'is_default' => false,
            ],
        ],
    ])->assertOk()
        ->assertJsonCount(4)
        ->assertJsonFragment([
            'product_id' => $firstProduct->getKey(),
            'price_list_id' => $wholesale->getKey(),
            'price' => 89.9,
            'is_default' => true,
        ])
        ->assertJsonFragment([
            'product_id' => $secondProduct->getKey(),
            'price_list_id' => $retail->getKey(),
            'price' => 79.9,
            'is_default' => true,
        ]);

    assertDatabaseHas('price_list_prices', [
        'product_id' => $firstProduct->getKey(),
        'price_list_id' => $retail->getKey(),
        'price' => 99.90,
        'is_default' => false,
    ]);

    assertDatabaseHas('price_list_prices', [
        'product_id' => $firstProduct->getKey(),
        'price_list_id' => $wholesale->getKey(),
        'price' => 89.90,
        'is_default' => true,
    ]);

    assertDatabaseHas('price_list_prices', [
        'product_id' => $secondProduct->getKey(),
        'price_list_id' => $retail->getKey(),
        'price' => 79.90,
        'is_default' => true,
    ]);

    assertDatabaseHas('price_list_prices', [
        'product_id' => $secondProduct->getKey(),
        'price_list_id' => $wholesale->getKey(),
        'price' => 69.90,
        'is_default' => false,
    ]);

    expect($existingPrice->fresh()->is_default)->toBeFalse();
});

it('validates duplicate tuples and multiple defaults in bulk upsert payloads', function () {
    config()->set('venditio.price_lists.enabled', true);
    $prefix = config('venditio.routes.api.v1.prefix');

    $product = pl_createProduct();
    $retail = PriceList::factory()->create(['name' => 'Retail']);
    $wholesale = PriceList::factory()->create(['name' => 'Wholesale']);

    postJson($prefix . '/price_list_prices/bulk/upsert', [
        'prices' => [
            [
                'product_id' => $product->getKey(),
                'price_list_id' => $retail->getKey(),
                'price' => 99.90,
                'is_default' => true,
            ],
            [
                'product_id' => $product->getKey(),
                'price_list_id' => $retail->getKey(),
                'price' => 89.90,
                'is_default' => false,
            ],
            [
                'product_id' => $product->getKey(),
                'price_list_id' => $wholesale->getKey(),
                'price' => 79.90,
                'is_default' => true,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['prices.1.price_list_id', 'prices.2.is_default']);
});

it('uses the default price list price in cart line pricing when enabled', function () {
    config()->set('venditio.price_lists.enabled', true);

    $product = pl_createProduct(inventoryPrice: 150);
    $retail = PriceList::factory()->create(['name' => 'Retail']);
    $wholesale = PriceList::factory()->create(['name' => 'Wholesale']);

    PriceListPrice::factory()->create([
        'product_id' => $product->getKey(),
        'price_list_id' => $retail->getKey(),
        'price' => 180,
        'is_default' => false,
    ]);

    PriceListPrice::factory()->create([
        'product_id' => $product->getKey(),
        'price_list_id' => $wholesale->getKey(),
        'price' => 95,
        'is_default' => true,
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}")
        ->assertOk()
        ->assertJsonPath('price_calculated.price', 95)
        ->assertJsonPath('price_calculated.price_final', 95)
        ->assertJsonPath('price_calculated.price_list.name', 'Wholesale')
        ->assertJsonMissingPath('price_lists');

    getJson(config('venditio.routes.api.v1.prefix') . "/products/{$product->getKey()}?include=price_lists")
        ->assertOk()
        ->assertJsonPath('price_calculated.price', 95)
        ->assertJsonPath('price_calculated.price_final', 95)
        ->assertJsonPath('price_lists.0.price_list.name', 'Retail')
        ->assertJsonPath('price_lists.1.price_list.name', 'Wholesale');

    $cartId = postJson(config('venditio.routes.api.v1.prefix') . '/carts', [
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()
        ->json('id');

    getJson(config('venditio.routes.api.v1.prefix') . '/carts/' . $cartId)
        ->assertOk()
        ->assertJsonPath('lines.0.unit_price', 95)
        ->assertJsonPath('lines.0.product_data.pricing.price_list.name', 'Wholesale');
});

it('allows host applications to override price resolver behavior', function () {
    config()->set('venditio.price_lists.enabled', true);

    app()->bind(ProductPriceResolverInterface::class, fn () => new class implements ProductPriceResolverInterface
    {
        public function resolve(Model $product): array
        {
            return [
                'unit_price' => 12.34,
                'purchase_price' => null,
                'price_includes_tax' => false,
                'price_list' => [
                    'id' => 999,
                    'name' => 'Custom Resolver',
                ],
            ];
        }
    });

    $product = pl_createProduct(inventoryPrice: 150);

    $cartId = postJson(config('venditio.routes.api.v1.prefix') . '/carts', [
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()
        ->json('id');

    getJson(config('venditio.routes.api.v1.prefix') . '/carts/' . $cartId)
        ->assertOk()
        ->assertJsonPath('lines.0.unit_price', 12.34)
        ->assertJsonPath('lines.0.product_data.pricing.price_list.name', 'Custom Resolver');
});
