<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Models\{Country, Currency, Municipality, Province, Region, ShippingZone, ShippingZoneMember};
use PictaStudio\Venditio\Shipping\MostSpecificZoneMatcher;

uses(RefreshDatabase::class);

function createMatcherGeography(): array
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

    return compact('country', 'region', 'province', 'municipality');
}

it('prefers the most specific matching zone', function () {
    ['country' => $country, 'region' => $region, 'province' => $province, 'municipality' => $municipality] = createMatcherGeography();

    $countryZone = ShippingZone::factory()->create(['code' => 'IT', 'priority' => 10]);
    $municipalityZone = ShippingZone::factory()->create(['code' => 'IT-RM-ROMA', 'priority' => 1]);

    ShippingZoneMember::query()->create([
        'shipping_zone_id' => $countryZone->getKey(),
        'zoneable_type' => 'country',
        'zoneable_id' => $country->getKey(),
    ]);

    ShippingZoneMember::query()->create([
        'shipping_zone_id' => $municipalityZone->getKey(),
        'zoneable_type' => 'municipality',
        'zoneable_id' => $municipality->getKey(),
    ]);

    $matcher = new MostSpecificZoneMatcher();

    $matched = $matcher->match([
        'country_id' => $country->getKey(),
        'region_id' => $region->getKey(),
        'province_id' => $province->getKey(),
        'municipality_id' => $municipality->getKey(),
    ]);

    expect($matched?->getKey())->toBe($municipalityZone->getKey());
});

it('returns fallback zone when no explicit zone matches', function () {
    ['country' => $country] = createMatcherGeography();

    $fallbackZone = ShippingZone::factory()->create([
        'code' => 'FALLBACK',
        'is_fallback' => true,
        'priority' => 100,
    ]);

    $matcher = new MostSpecificZoneMatcher();

    $matched = $matcher->match([
        'country_id' => $country->getKey(),
    ]);

    expect($matched?->getKey())->toBe($fallbackZone->getKey());
});
