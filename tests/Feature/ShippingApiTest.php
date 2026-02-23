<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Country, CountryTaxClass, Currency, Municipality, Product, Province, Region, ShippingCarrier, ShippingRate, ShippingRateTier, ShippingZone, ShippingZoneMember, TaxClass, User};

use function Pest\Laravel\{getJson, patchJson, postJson};

uses(RefreshDatabase::class);

beforeEach(function () {
    if (Schema::hasTable('users')) {
        return;
    }

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('first_name');
        $table->string('last_name');
        $table->string('email')->unique();
        $table->string('phone')->nullable();
        $table->timestamps();
    });
});

function setupShippingGeographyAndTaxes(): array
{
    $currency = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'EUR', 'exchange_rate' => 1, 'is_enabled' => true, 'is_default' => true]
    );

    $country = Country::query()->create([
        'name' => 'Italy',
        'iso_2' => 'IT',
        'iso_3' => 'ITA',
        'phone_code' => '+39',
        'currency_id' => $currency->getKey(),
        'flag_emoji' => 'it',
        'capital' => 'Rome',
        'native' => 'Italia',
    ]);

    $region = Region::query()->create([
        'country_id' => $country->getKey(),
        'name' => 'Lazio',
        'code' => 'LAZ',
    ]);

    $province = Province::query()->create([
        'region_id' => $region->getKey(),
        'name' => 'Roma',
        'code' => 'RM',
    ]);

    $municipality = Municipality::query()->create([
        'province_id' => $province->getKey(),
        'name' => 'Roma',
        'zip' => '00100',
    ]);

    $taxClass = TaxClass::factory()->create();

    CountryTaxClass::query()->create([
        'country_id' => $country->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'rate' => 22,
    ]);

    return compact('country', 'region', 'province', 'municipality', 'taxClass');
}

function createShippingUser(): User
{
    return User::query()->create([
        'first_name' => 'Shipping',
        'last_name' => 'User',
        'email' => 'shipping-user@example.test',
        'phone' => '123456789',
    ]);
}

function createShippingProduct(TaxClass $taxClass): Product
{
    $product = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
        'weight' => 1,
        'length' => 10,
        'width' => 10,
        'height' => 10,
    ]);

    $product->inventory()->updateOrCreate([], [
        'stock' => 100,
        'stock_reserved' => 0,
        'stock_available' => 100,
        'stock_min' => 0,
        'price' => 100,
        'price_includes_tax' => false,
        'purchase_price' => null,
    ]);

    return $product->refresh();
}

function setupShippingRatesForMunicipality(Municipality $municipality): array
{
    $carrierA = ShippingCarrier::factory()->create([
        'code' => 'EXPRESS',
        'name' => 'Express',
        'volumetric_divisor' => 5000,
        'weight_rounding_step_kg' => 0.5,
        'weight_rounding_mode' => 'ceil',
    ]);

    $carrierB = ShippingCarrier::factory()->create([
        'code' => 'ECONOMY',
        'name' => 'Economy',
        'volumetric_divisor' => 5000,
        'weight_rounding_step_kg' => 0.5,
        'weight_rounding_mode' => 'ceil',
    ]);

    $zone = ShippingZone::factory()->create([
        'code' => 'IT-ROMA',
        'name' => 'Roma Zone',
        'priority' => 100,
    ]);

    ShippingZoneMember::query()->create([
        'shipping_zone_id' => $zone->getKey(),
        'zoneable_type' => 'municipality',
        'zoneable_id' => $municipality->getKey(),
    ]);

    $rateA = ShippingRate::factory()->create([
        'shipping_carrier_id' => $carrierA->getKey(),
        'shipping_zone_id' => $zone->getKey(),
        'name' => 'Express Standard',
        'base_fee' => 5,
    ]);

    ShippingRateTier::factory()->create([
        'shipping_rate_id' => $rateA->getKey(),
        'from_weight_kg' => 0,
        'to_weight_kg' => null,
        'additional_fee' => 5,
    ]);

    $rateB = ShippingRate::factory()->create([
        'shipping_carrier_id' => $carrierB->getKey(),
        'shipping_zone_id' => $zone->getKey(),
        'name' => 'Economy Light',
        'base_fee' => 3,
    ]);

    ShippingRateTier::factory()->create([
        'shipping_rate_id' => $rateB->getKey(),
        'from_weight_kg' => 0,
        'to_weight_kg' => 2,
        'additional_fee' => 1,
    ]);

    return compact('carrierA', 'carrierB', 'zone', 'rateA', 'rateB');
}

it('returns shipping quotes and auto-selects the cheapest shipping rate', function () {
    ['country' => $country, 'region' => $region, 'province' => $province, 'municipality' => $municipality, 'taxClass' => $taxClass] = setupShippingGeographyAndTaxes();
    $user = createShippingUser();
    $product = createShippingProduct($taxClass);
    ['rateA' => $rateA, 'rateB' => $rateB] = setupShippingRatesForMunicipality($municipality);

    $prefix = config('venditio.routes.api.v1.prefix');

    $response = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'addresses' => [
            'shipping' => [
                'country_id' => $country->getKey(),
                'region_id' => $region->getKey(),
                'province_id' => $province->getKey(),
                'municipality_id' => $municipality->getKey(),
            ],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated();

    $cartId = (int) $response->json('id');

    $response->assertJsonPath('shipping_rate_id', $rateB->getKey())
        ->assertJsonPath('shipping_fee', 4);

    getJson($prefix . '/carts/' . $cartId . '/shipping_quotes')
        ->assertOk()
        ->assertJsonPath('0.shipping_rate_id', $rateB->getKey())
        ->assertJsonPath('0.amount', 4)
        ->assertJsonPath('1.shipping_rate_id', $rateA->getKey())
        ->assertJsonPath('1.amount', 10);
});

it('updates shipping selection and recalculates when selected rate becomes invalid', function () {
    ['country' => $country, 'region' => $region, 'province' => $province, 'municipality' => $municipality, 'taxClass' => $taxClass] = setupShippingGeographyAndTaxes();
    $user = createShippingUser();
    $product = createShippingProduct($taxClass);
    ['rateA' => $rateA, 'rateB' => $rateB] = setupShippingRatesForMunicipality($municipality);

    $prefix = config('venditio.routes.api.v1.prefix');

    $createResponse = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'addresses' => [
            'shipping' => [
                'country_id' => $country->getKey(),
                'region_id' => $region->getKey(),
                'province_id' => $province->getKey(),
                'municipality_id' => $municipality->getKey(),
            ],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated();

    $cartId = (int) $createResponse->json('id');

    postJson($prefix . '/carts/' . $cartId . '/shipping_selection', [
        'shipping_rate_id' => $rateA->getKey(),
    ])->assertOk()
        ->assertJsonPath('shipping_rate_id', $rateA->getKey())
        ->assertJsonPath('shipping_fee', 10);

    postJson($prefix . '/carts/' . $cartId . '/shipping_selection', [
        'shipping_rate_id' => $rateB->getKey(),
    ])->assertOk()
        ->assertJsonPath('shipping_rate_id', $rateB->getKey())
        ->assertJsonPath('shipping_fee', 4);

    $lineId = (int) getJson($prefix . '/carts/' . $cartId)
        ->assertOk()
        ->json('lines.0.id');

    patchJson($prefix . '/carts/' . $cartId . '/update_lines', [
        'lines' => [
            ['id' => $lineId, 'qty' => 3],
        ],
    ])->assertOk()
        ->assertJsonPath('shipping_rate_id', $rateA->getKey())
        ->assertJsonPath('shipping_fee', 10);
});

it('copies selected shipping fields and snapshot when converting cart to order', function () {
    ['country' => $country, 'region' => $region, 'province' => $province, 'municipality' => $municipality, 'taxClass' => $taxClass] = setupShippingGeographyAndTaxes();
    $user = createShippingUser();
    $product = createShippingProduct($taxClass);
    ['carrierA' => $carrierA, 'rateA' => $rateA] = setupShippingRatesForMunicipality($municipality);

    $prefix = config('venditio.routes.api.v1.prefix');

    $createResponse = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'addresses' => [
            'shipping' => [
                'country_id' => $country->getKey(),
                'region_id' => $region->getKey(),
                'province_id' => $province->getKey(),
                'municipality_id' => $municipality->getKey(),
            ],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated();

    $cartId = (int) $createResponse->json('id');

    postJson($prefix . '/carts/' . $cartId . '/shipping_selection', [
        'shipping_rate_id' => $rateA->getKey(),
    ])->assertOk();

    postJson($prefix . '/orders', [
        'cart_id' => $cartId,
    ])->assertOk()
        ->assertJsonPath('shipping_rate_id', $rateA->getKey())
        ->assertJsonPath('shipping_carrier_id', $carrierA->getKey())
        ->assertJsonPath('courier_code', $carrierA->code)
        ->assertJsonPath('shipping_quote_snapshot.shipping_rate_id', $rateA->getKey());
});
