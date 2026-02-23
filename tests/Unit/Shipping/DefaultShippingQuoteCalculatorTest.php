<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Models\{Country, Currency, ShippingCarrier, ShippingRate, ShippingRateTier, ShippingZone, ShippingZoneMember};
use PictaStudio\Venditio\Shipping\{DefaultChargeableWeightCalculator, DefaultShippingQuoteCalculator, MostSpecificZoneMatcher};
use function PictaStudio\Venditio\Helpers\Functions\get_fresh_model_instance;

uses(RefreshDatabase::class);

it('builds and sorts shipping quotes for a cart', function () {
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

    $zone = ShippingZone::factory()->create(['code' => 'IT']);

    ShippingZoneMember::query()->create([
        'shipping_zone_id' => $zone->getKey(),
        'zoneable_type' => 'country',
        'zoneable_id' => $country->getKey(),
    ]);

    $carrierA = ShippingCarrier::factory()->create(['code' => 'A']);
    $carrierB = ShippingCarrier::factory()->create(['code' => 'B']);

    $rateA = ShippingRate::factory()->create([
        'shipping_carrier_id' => $carrierA->getKey(),
        'shipping_zone_id' => $zone->getKey(),
        'base_fee' => 5,
    ]);

    ShippingRateTier::factory()->create([
        'shipping_rate_id' => $rateA->getKey(),
        'from_weight_kg' => 0,
        'to_weight_kg' => null,
        'additional_fee' => 3,
    ]);

    $rateB = ShippingRate::factory()->create([
        'shipping_carrier_id' => $carrierB->getKey(),
        'shipping_zone_id' => $zone->getKey(),
        'base_fee' => 2,
    ]);

    ShippingRateTier::factory()->create([
        'shipping_rate_id' => $rateB->getKey(),
        'from_weight_kg' => 0,
        'to_weight_kg' => null,
        'additional_fee' => 10,
    ]);

    $cart = get_fresh_model_instance('cart')->fill([
        'addresses' => [
            'shipping' => [
                'country_id' => $country->getKey(),
            ],
        ],
    ]);

    $line = get_fresh_model_instance('cart_line')->fill([
        'qty' => 1,
        'total_final_price' => 100,
        'product_data' => [
            'weight' => 1,
            'length' => 10,
            'width' => 10,
            'height' => 10,
        ],
    ]);

    $cart->setRelation('lines', new Collection([$line]));

    $calculator = new DefaultShippingQuoteCalculator(
        new MostSpecificZoneMatcher(),
        new DefaultChargeableWeightCalculator(),
    );

    $quotes = $calculator->calculateForCart($cart);

    expect($quotes)->toHaveCount(2)
        ->and((int) $quotes->first()['shipping_rate_id'])->toBe($rateA->getKey())
        ->and((float) $quotes->first()['amount'])->toBe(8.0)
        ->and((int) $quotes->last()['shipping_rate_id'])->toBe($rateB->getKey())
        ->and((float) $quotes->last()['amount'])->toBe(12.0);
});
