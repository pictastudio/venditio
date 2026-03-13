<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Models\{Country, Currency, Municipality, Province, Region};

use function Pest\Laravel\{deleteJson, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

function createCountryForMunicipality(string $iso2, string $iso3, string $name): Country
{
    $currencyId = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'EUR', 'exchange_rate' => 1, 'is_enabled' => true, 'is_default' => false]
    )->getKey();

    return Country::query()->create([
        'name' => $name,
        'iso_2' => $iso2,
        'iso_3' => $iso3,
        'phone_code' => '+39',
        'currency_id' => $currencyId,
        'flag_emoji' => mb_strtolower($iso2),
        'capital' => 'Capital',
        'native' => $name,
    ]);
}

function createRegionForMunicipality(Country $country, string $name, ?string $code = null): Region
{
    return Region::query()->create([
        'country_id' => $country->getKey(),
        'name' => $name,
        'code' => $code,
    ]);
}

function createProvinceForMunicipality(Region $region, string $name, ?string $code = null): Province
{
    return Province::query()->create([
        'region_id' => $region->getKey(),
        'name' => $name,
        'code' => $code,
    ]);
}

it('lists municipalities and filters by province_id', function () {
    $countryA = createCountryForMunicipality('IT', 'ITA', 'Italy');
    $countryB = createCountryForMunicipality('DE', 'DEU', 'Germany');

    $regionA = createRegionForMunicipality($countryA, 'Lombardia', 'LOM');
    $regionB = createRegionForMunicipality($countryB, 'Berlin', 'BER');

    $provinceA = createProvinceForMunicipality($regionA, 'Milano', 'MI');
    $provinceB = createProvinceForMunicipality($regionB, 'Berlin', 'BE');

    $municipalityA = Municipality::query()->create([
        'province_id' => $provinceA->getKey(),
        'name' => 'Rome',
    ]);

    $municipalityB = Municipality::query()->create([
        'province_id' => $provinceB->getKey(),
        'name' => 'Berlin',
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    $response = getJson($prefix . '/municipalities?all=1&province_id=' . $provinceA->getKey())
        ->assertOk();

    $json = $response->json();
    $ids = collect($json)->pluck('id')->filter()->values();

    if ($ids->isEmpty() && is_array(data_get($json, 'data'))) {
        $ids = collect(data_get($json, 'data'))->pluck('id')->filter()->values();
    }

    expect($ids)->toContain($municipalityA->getKey())
        ->not->toContain($municipalityB->getKey());
});

it('shows a municipality', function () {
    $country = createCountryForMunicipality('FR', 'FRA', 'France');
    $region = createRegionForMunicipality($country, 'Ile-de-France', 'IDF');
    $province = createProvinceForMunicipality($region, 'Paris', 'PA');

    $municipality = Municipality::query()->create([
        'province_id' => $province->getKey(),
        'name' => 'Paris',
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . '/municipalities/' . $municipality->getKey())
        ->assertOk()
        ->assertJsonPath('id', $municipality->getKey())
        ->assertJsonPath('province_id', $province->getKey());
});

it('exposes countries as read-only api resource', function () {
    $country = createCountryForMunicipality('ES', 'ESP', 'Spain');
    $prefix = config('venditio.routes.api.v1.prefix');

    getJson($prefix . '/countries')->assertOk();
    getJson($prefix . '/countries/' . $country->getKey())->assertOk();

    postJson($prefix . '/countries', [])->assertStatus(405);
    patchJson($prefix . '/countries/' . $country->getKey(), [])->assertStatus(405);
    deleteJson($prefix . '/countries/' . $country->getKey())->assertStatus(405);
});

it('lists regions and filters by country_id', function () {
    $countryA = createCountryForMunicipality('IT', 'ITA', 'Italy');
    $countryB = createCountryForMunicipality('DE', 'DEU', 'Germany');

    $regionA = createRegionForMunicipality($countryA, 'Lazio', 'LAZ');
    $regionB = createRegionForMunicipality($countryB, 'Bayern', 'BAY');

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/regions?all=1&country_id=' . $countryA->getKey())
        ->assertOk();

    $ids = collect($response->json())->pluck('id')->filter()->values();
    if ($ids->isEmpty() && is_array(data_get($response->json(), 'data'))) {
        $ids = collect(data_get($response->json(), 'data'))->pluck('id')->filter()->values();
    }

    expect($ids)->toContain($regionA->getKey())
        ->not->toContain($regionB->getKey());
});

it('lists provinces and filters by region_id', function () {
    $country = createCountryForMunicipality('IT', 'ITA', 'Italy');
    $regionA = createRegionForMunicipality($country, 'Lombardia', 'LOM');
    $regionB = createRegionForMunicipality($country, 'Lazio', 'LAZ');

    $provinceA = createProvinceForMunicipality($regionA, 'Milano', 'MI');
    $provinceB = createProvinceForMunicipality($regionB, 'Roma', 'RM');

    $response = getJson(config('venditio.routes.api.v1.prefix') . '/provinces?all=1&region_id=' . $regionA->getKey())
        ->assertOk();

    $ids = collect($response->json())->pluck('id')->filter()->values();
    if ($ids->isEmpty() && is_array(data_get($response->json(), 'data'))) {
        $ids = collect(data_get($response->json(), 'data'))->pluck('id')->filter()->values();
    }

    expect($ids)->toContain($provinceA->getKey())
        ->not->toContain($provinceB->getKey());
});

it('allows connecting an address to a province', function () {
    $country = createCountryForMunicipality('IT', 'ITA', 'Italy');
    $region = createRegionForMunicipality($country, 'Lombardia', 'LOM');
    $province = createProvinceForMunicipality($region, 'Milano', 'MI');

    Municipality::query()->create([
        'province_id' => $province->getKey(),
        'name' => 'Milan',
    ]);

    $payload = [
        'addressable_type' => 'user',
        'addressable_id' => 1,
        'country_id' => $country->getKey(),
        'province_id' => $province->getKey(),
        'type' => 'shipping',
        'is_default' => true,
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => 'mario.rossi@example.test',
        'sex' => 'm',
        'phone' => '123456789',
        'fiscal_code' => 'RSSMRA80A01F205X',
        'sdi' => 'ABC1234',
        'pec' => 'mario.rossi@pec.example.test',
        'address_line_1' => 'Via Roma 1',
        'city' => 'Milano',
        'state' => 'MI',
        'zip' => '20100',
    ];

    postJson(config('venditio.routes.api.v1.prefix') . '/addresses', $payload)
        ->assertCreated()
        ->assertJsonPath('country_id', $country->getKey())
        ->assertJsonPath('province_id', $province->getKey())
        ->assertJsonPath('sdi', 'ABC1234')
        ->assertJsonPath('pec', 'mario.rossi@pec.example.test');
});

it('rejects invalid pec when creating an address', function () {
    $country = createCountryForMunicipality('IT', 'ITA', 'Italy');
    $region = createRegionForMunicipality($country, 'Lombardia', 'LOM');
    $province = createProvinceForMunicipality($region, 'Milano', 'MI');

    postJson(config('venditio.routes.api.v1.prefix') . '/addresses', [
        'addressable_type' => 'user',
        'addressable_id' => 1,
        'country_id' => $country->getKey(),
        'province_id' => $province->getKey(),
        'type' => 'billing',
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => 'mario.rossi@example.test',
        'sex' => 'm',
        'phone' => '123456789',
        'fiscal_code' => 'RSSMRA80A01F205X',
        'address_line_1' => 'Via Roma 1',
        'city' => 'Milano',
        'state' => 'MI',
        'zip' => '20100',
        'pec' => 'not-an-email',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['pec']);
});
