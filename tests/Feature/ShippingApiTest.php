<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\{DiscountType, ProductStatus};
use PictaStudio\Venditio\Models\{Country, CountryTaxClass, Currency, Discount, Order, Product, Province, Region, ShippingMethod, ShippingMethodZone, ShippingZone, TaxClass};

use function Pest\Laravel\{assertDatabaseHas, assertDatabaseMissing, deleteJson, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

function ship_createCountry(string $iso2 = 'IT'): Country
{
    $currencyId = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'EUR', 'exchange_rate' => 1, 'is_enabled' => true, 'is_default' => false]
    )->getKey();

    return Country::query()->create([
        'name' => 'Country ' . $iso2,
        'iso_2' => $iso2,
        'iso_3' => $iso2 . 'A',
        'phone_code' => '+39',
        'currency_id' => $currencyId,
        'flag_emoji' => mb_strtolower($iso2),
        'capital' => 'Capital ' . $iso2,
        'native' => 'Native ' . $iso2,
    ]);
}

function ship_createRegion(Country $country, string $name = 'Lazio', string $code = 'LAZ'): Region
{
    return Region::query()->create([
        'country_id' => $country->getKey(),
        'name' => $name,
        'code' => $code,
    ]);
}

function ship_createProvince(Region $region, string $name = 'Roma', string $code = 'RM'): Province
{
    return Province::query()->create([
        'region_id' => $region->getKey(),
        'name' => $name,
        'code' => $code,
    ]);
}

function ship_setupTaxEnvironment(TaxClass $taxClass, Country $country, float $rate = 22): void
{
    CountryTaxClass::query()->create([
        'country_id' => $country->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'rate' => $rate,
    ]);
}

function ship_createProduct(TaxClass $taxClass, array $attributes = []): Product
{
    $product = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
        'length' => 100,
        'width' => 50,
        'height' => 40,
        'weight' => 5,
        ...$attributes,
    ]);

    $product->inventory()->updateOrCreate([], [
        'currency_id' => Currency::query()->firstOrFail()->getKey(),
        'stock' => 100,
        'stock_reserved' => 0,
        'stock_available' => 100,
        'stock_min' => 0,
        'price' => 100,
        'purchase_price' => 40,
        'price_includes_tax' => false,
    ]);

    return $product->refresh();
}

function ship_createShippingMethod(array $attributes = []): ShippingMethod
{
    return ShippingMethod::query()->create([
        'code' => 'METHOD-' . fake()->unique()->numerify('###'),
        'name' => 'Courier ' . fake()->numerify('###'),
        'active' => true,
        'flat_fee' => 0,
        'volumetric_divisor' => 5000,
        ...$attributes,
    ]);
}

function ship_createShippingZone(array $attributes = [], array $countryIds = [], array $regionIds = [], array $provinceIds = []): ShippingZone
{
    $zone = ShippingZone::query()->create([
        'code' => 'ZONE-' . fake()->unique()->numerify('###'),
        'name' => 'Zone ' . fake()->numerify('###'),
        'active' => true,
        'priority' => 0,
        ...$attributes,
    ]);

    $zone->countries()->sync($countryIds);
    $zone->regions()->sync($regionIds);
    $zone->provinces()->sync($provinceIds);

    return $zone->refresh();
}

function ship_attachMethodToZone(ShippingMethod $shippingMethod, ShippingZone $shippingZone, array $attributes = []): ShippingMethodZone
{
    return ShippingMethodZone::query()->create([
        'shipping_method_id' => $shippingMethod->getKey(),
        'shipping_zone_id' => $shippingZone->getKey(),
        'active' => true,
        'rate_tiers' => [
            ['max_weight' => 100, 'fee' => 10],
        ],
        'over_weight_price_per_kg' => 1.5,
        ...$attributes,
    ]);
}

it('provides full crud for shipping methods', function () {
    $prefix = config('venditio.routes.api.v1.prefix');

    $created = postJson($prefix . '/shipping_methods', [
        'code' => 'BRT',
        'name' => 'BRT',
        'active' => true,
        'flat_fee' => 9.5,
        'volumetric_divisor' => 5000,
    ])->assertCreated();

    $shippingMethodId = $created->json('id');

    assertDatabaseHas('shipping_methods', [
        'id' => $shippingMethodId,
        'code' => 'BRT',
        'name' => 'BRT',
        'flat_fee' => 9.50,
        'volumetric_divisor' => 5000,
    ]);

    getJson($prefix . '/shipping_methods?all=1')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $shippingMethodId,
            'code' => 'BRT',
        ]);

    patchJson($prefix . '/shipping_methods/' . $shippingMethodId, [
        'name' => 'BRT Updated',
        'volumetric_divisor' => 4000,
    ])->assertOk()
        ->assertJsonPath('name', 'BRT Updated')
        ->assertJsonPath('volumetric_divisor', 4000);

    deleteJson($prefix . '/shipping_methods/' . $shippingMethodId)->assertNoContent();

    assertDatabaseMissing('shipping_methods', [
        'id' => $shippingMethodId,
        'deleted_at' => null,
    ]);
});

it('stores and updates shipping zones with synced countries regions and provinces', function () {
    $prefix = config('venditio.routes.api.v1.prefix');
    $country = ship_createCountry('IT');
    $region = ship_createRegion($country, 'Lazio', 'LAZ');
    $province = ship_createProvince($region, 'Roma', 'RM');
    $otherProvince = ship_createProvince($region, 'Viterbo', 'VT');

    $created = postJson($prefix . '/shipping_zones', [
        'code' => 'ITALY-ROME',
        'name' => 'Italy Rome',
        'priority' => 10,
        'country_ids' => [$country->getKey()],
        'region_ids' => [$region->getKey()],
        'province_ids' => [$province->getKey()],
    ])->assertCreated()
        ->assertJsonPath('country_ids.0', $country->getKey())
        ->assertJsonPath('region_ids.0', $region->getKey())
        ->assertJsonPath('province_ids.0', $province->getKey());

    $shippingZoneId = $created->json('id');

    assertDatabaseHas('shipping_zone_country', [
        'shipping_zone_id' => $shippingZoneId,
        'country_id' => $country->getKey(),
    ]);
    assertDatabaseHas('shipping_zone_region', [
        'shipping_zone_id' => $shippingZoneId,
        'region_id' => $region->getKey(),
    ]);
    assertDatabaseHas('shipping_zone_province', [
        'shipping_zone_id' => $shippingZoneId,
        'province_id' => $province->getKey(),
    ]);

    patchJson($prefix . '/shipping_zones/' . $shippingZoneId, [
        'province_ids' => [$otherProvince->getKey()],
    ])->assertOk()
        ->assertJsonPath('province_ids.0', $otherProvince->getKey());

    assertDatabaseMissing('shipping_zone_province', [
        'shipping_zone_id' => $shippingZoneId,
        'province_id' => $province->getKey(),
    ]);
    assertDatabaseHas('shipping_zone_province', [
        'shipping_zone_id' => $shippingZoneId,
        'province_id' => $otherProvince->getKey(),
    ]);
});

it('validates shipping method zone tuples and ordered rate tiers', function () {
    $prefix = config('venditio.routes.api.v1.prefix');
    $shippingMethod = ship_createShippingMethod();
    $shippingZone = ship_createShippingZone();

    ship_attachMethodToZone($shippingMethod, $shippingZone);

    postJson($prefix . '/shipping_method_zones', [
        'shipping_method_id' => $shippingMethod->getKey(),
        'shipping_zone_id' => $shippingZone->getKey(),
        'rate_tiers' => [
            ['max_weight' => 20, 'fee' => 10],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['shipping_zone_id']);

    $otherShippingZone = ship_createShippingZone(['code' => 'OTHER-ZONE']);

    postJson($prefix . '/shipping_method_zones', [
        'shipping_method_id' => $shippingMethod->getKey(),
        'shipping_zone_id' => $otherShippingZone->getKey(),
        'rate_tiers' => [
            ['max_weight' => 20, 'fee' => 10],
            ['max_weight' => 10, 'fee' => 7],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['rate_tiers.1.max_weight']);
});

it('calculates flat shipping fees and weights on carts', function () {
    config()->set('venditio.shipping.strategy', 'flat');

    $country = ship_createCountry('IT');
    $region = ship_createRegion($country);
    $province = ship_createProvince($region);
    $taxClass = TaxClass::factory()->create();
    ship_setupTaxEnvironment($taxClass, $country);
    $product = ship_createProduct($taxClass);
    $shippingMethod = ship_createShippingMethod([
        'flat_fee' => 12,
        'volumetric_divisor' => 5000,
    ]);

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/carts', [
        'shipping_method_id' => $shippingMethod->getKey(),
        'addresses' => [
            'billing' => ['country_id' => $country->getKey()],
            'shipping' => [
                'country_id' => $country->getKey(),
                'province_id' => $province->getKey(),
            ],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated();

    $response->assertJsonPath('shipping_fee', 12)
        ->assertJsonPath('specific_weight', 5)
        ->assertJsonPath('volumetric_weight', 40)
        ->assertJsonPath('chargeable_weight', 40)
        ->assertJsonPath('total_final', 134);
});

it('prefers the most specific shipping zone and snapshots shipping data on orders', function () {
    config()->set('venditio.shipping.strategy', 'zones');

    $country = ship_createCountry('IT');
    $region = ship_createRegion($country, 'Lazio', 'LAZ');
    $province = ship_createProvince($region, 'Roma', 'RM');
    $taxClass = TaxClass::factory()->create();
    ship_setupTaxEnvironment($taxClass, $country);
    $product = ship_createProduct($taxClass);
    $shippingMethod = ship_createShippingMethod(['code' => 'GLS', 'name' => 'GLS']);
    $countryZone = ship_createShippingZone(['code' => 'IT', 'name' => 'Italy'], [$country->getKey()]);
    $provinceZone = ship_createShippingZone(['code' => 'RM', 'name' => 'Rome', 'priority' => 10], [], [], [$province->getKey()]);

    ship_attachMethodToZone($shippingMethod, $countryZone, [
        'rate_tiers' => [['max_weight' => 100, 'fee' => 15]],
    ]);
    ship_attachMethodToZone($shippingMethod, $provinceZone, [
        'rate_tiers' => [['max_weight' => 100, 'fee' => 7]],
    ]);

    $cart = postJson(config('venditio.routes.api.v1.prefix') . '/carts', [
        'shipping_method_id' => $shippingMethod->getKey(),
        'addresses' => [
            'billing' => ['country_id' => $country->getKey()],
            'shipping' => [
                'country_id' => $country->getKey(),
                'province_id' => $province->getKey(),
            ],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()
        ->assertJsonPath('shipping_zone_id', $provinceZone->getKey())
        ->assertJsonPath('shipping_fee', 7);

    $order = postJson(config('venditio.routes.api.v1.prefix') . '/orders', [
        'cart_id' => $cart->json('id'),
    ])->assertOk();

    $order->assertJsonPath('shipping_method_id', $shippingMethod->getKey())
        ->assertJsonPath('shipping_zone_id', $provinceZone->getKey())
        ->assertJsonPath('shipping_method_data.id', $shippingMethod->getKey())
        ->assertJsonPath('shipping_zone_data.id', $provinceZone->getKey())
        ->assertJsonPath('shipping_fee', 7)
        ->assertJsonPath('chargeable_weight', 40);
});

it('falls back from region to country when province-specific zones are missing', function () {
    config()->set('venditio.shipping.strategy', 'zones');

    $country = ship_createCountry('IT');
    $region = ship_createRegion($country, 'Lazio', 'LAZ');
    $province = ship_createProvince($region, 'Roma', 'RM');
    $taxClass = TaxClass::factory()->create();
    ship_setupTaxEnvironment($taxClass, $country);
    $product = ship_createProduct($taxClass);
    $shippingMethod = ship_createShippingMethod();
    $countryZone = ship_createShippingZone(['code' => 'IT-COUNTRY'], [$country->getKey()]);
    $regionZone = ship_createShippingZone(['code' => 'IT-LAZIO', 'priority' => 5], [], [$region->getKey()]);

    ship_attachMethodToZone($shippingMethod, $countryZone, [
        'rate_tiers' => [['max_weight' => 100, 'fee' => 15]],
    ]);
    ship_attachMethodToZone($shippingMethod, $regionZone, [
        'rate_tiers' => [['max_weight' => 100, 'fee' => 10]],
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . '/carts', [
        'shipping_method_id' => $shippingMethod->getKey(),
        'addresses' => [
            'billing' => ['country_id' => $country->getKey()],
            'shipping' => [
                'country_id' => $country->getKey(),
                'province_id' => $province->getKey(),
            ],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()
        ->assertJsonPath('shipping_zone_id', $regionZone->getKey())
        ->assertJsonPath('shipping_fee', 10);
});

it('changes chargeable weight and fee when the courier volumetric divisor changes', function () {
    config()->set('venditio.shipping.strategy', 'zones');

    $country = ship_createCountry('IT');
    $region = ship_createRegion($country, 'Lazio', 'LAZ');
    $province = ship_createProvince($region, 'Roma', 'RM');
    $taxClass = TaxClass::factory()->create();
    ship_setupTaxEnvironment($taxClass, $country);
    $product = ship_createProduct($taxClass);
    $firstMethod = ship_createShippingMethod([
        'code' => 'DHL',
        'volumetric_divisor' => 5000,
    ]);
    $secondMethod = ship_createShippingMethod([
        'code' => 'BRT',
        'volumetric_divisor' => 4000,
    ]);
    $zone = ship_createShippingZone(['code' => 'IT-RM'], [], [], [$province->getKey()]);

    ship_attachMethodToZone($firstMethod, $zone, [
        'rate_tiers' => [
            ['max_weight' => 45, 'fee' => 18],
            ['max_weight' => 60, 'fee' => 24],
        ],
    ]);
    ship_attachMethodToZone($secondMethod, $zone, [
        'rate_tiers' => [
            ['max_weight' => 45, 'fee' => 18],
            ['max_weight' => 60, 'fee' => 24],
        ],
    ]);

    $cart = postJson(config('venditio.routes.api.v1.prefix') . '/carts', [
        'shipping_method_id' => $firstMethod->getKey(),
        'addresses' => [
            'billing' => ['country_id' => $country->getKey()],
            'shipping' => [
                'country_id' => $country->getKey(),
                'province_id' => $province->getKey(),
            ],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()
        ->assertJsonPath('chargeable_weight', 40)
        ->assertJsonPath('shipping_fee', 18);

    patchJson(config('venditio.routes.api.v1.prefix') . '/carts/' . $cart->json('id'), [
        'shipping_method_id' => $secondMethod->getKey(),
    ])->assertOk()
        ->assertJsonPath('chargeable_weight', 50)
        ->assertJsonPath('shipping_fee', 24);
});

it('returns validation errors when the selected shipping method does not match any zone for a complete destination', function () {
    config()->set('venditio.shipping.strategy', 'zones');

    $country = ship_createCountry('IT');
    $region = ship_createRegion($country, 'Lazio', 'LAZ');
    $province = ship_createProvince($region, 'Roma', 'RM');
    $otherProvince = ship_createProvince($region, 'Viterbo', 'VT');
    $taxClass = TaxClass::factory()->create();
    ship_setupTaxEnvironment($taxClass, $country);
    $product = ship_createProduct($taxClass);
    $shippingMethod = ship_createShippingMethod();
    $zone = ship_createShippingZone(['code' => 'VT'], [], [], [$otherProvince->getKey()]);

    ship_attachMethodToZone($shippingMethod, $zone);

    postJson(config('venditio.routes.api.v1.prefix') . '/carts', [
        'shipping_method_id' => $shippingMethod->getKey(),
        'addresses' => [
            'billing' => ['country_id' => $country->getKey()],
            'shipping' => [
                'country_id' => $country->getKey(),
                'province_id' => $province->getKey(),
            ],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['shipping_method_id']);
});

it('keeps shipping fee at zero when method or destination data are incomplete', function () {
    config()->set('venditio.shipping.strategy', 'zones');

    $country = ship_createCountry('IT');
    $region = ship_createRegion($country, 'Lazio', 'LAZ');
    $province = ship_createProvince($region, 'Roma', 'RM');
    $taxClass = TaxClass::factory()->create();
    ship_setupTaxEnvironment($taxClass, $country);
    $product = ship_createProduct($taxClass);
    $shippingMethod = ship_createShippingMethod();
    $zone = ship_createShippingZone(['code' => 'RM'], [], [], [$province->getKey()]);

    ship_attachMethodToZone($shippingMethod, $zone, [
        'rate_tiers' => [['max_weight' => 100, 'fee' => 8]],
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . '/carts', [
        'addresses' => [
            'billing' => ['country_id' => $country->getKey()],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()
        ->assertJsonPath('shipping_fee', 0);

    postJson(config('venditio.routes.api.v1.prefix') . '/carts', [
        'shipping_method_id' => $shippingMethod->getKey(),
        'addresses' => [
            'billing' => ['country_id' => $country->getKey()],
            'shipping' => [
                'country_id' => $country->getKey(),
            ],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()
        ->assertJsonPath('shipping_fee', 0)
        ->assertJsonPath('shipping_zone_id', null);
});

it('applies free shipping discounts after zone-based fee calculation', function () {
    config()->set('venditio.shipping.strategy', 'zones');

    $country = ship_createCountry('IT');
    $region = ship_createRegion($country, 'Lazio', 'LAZ');
    $province = ship_createProvince($region, 'Roma', 'RM');
    $taxClass = TaxClass::factory()->create();
    ship_setupTaxEnvironment($taxClass, $country);
    $product = ship_createProduct($taxClass);
    $shippingMethod = ship_createShippingMethod();
    $zone = ship_createShippingZone(['code' => 'RM'], [], [], [$province->getKey()]);

    ship_attachMethodToZone($shippingMethod, $zone, [
        'rate_tiers' => [['max_weight' => 100, 'fee' => 9]],
    ]);

    Discount::query()->create([
        'discountable_type' => null,
        'discountable_id' => null,
        'type' => DiscountType::Fixed,
        'value' => 0,
        'name' => 'Free shipping',
        'code' => 'FREESHIP',
        'active' => true,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
        'apply_to_cart_total' => true,
        'apply_once_per_cart' => false,
        'max_uses_per_user' => null,
        'one_per_user' => false,
        'free_shipping' => true,
        'minimum_order_total' => null,
    ]);

    postJson(config('venditio.routes.api.v1.prefix') . '/carts', [
        'shipping_method_id' => $shippingMethod->getKey(),
        'discount_code' => 'FREESHIP',
        'addresses' => [
            'billing' => ['country_id' => $country->getKey()],
            'shipping' => [
                'country_id' => $country->getKey(),
                'province_id' => $province->getKey(),
            ],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()
        ->assertJsonPath('discount_code', 'FREESHIP')
        ->assertJsonPath('shipping_fee', 0);
});
