<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Models\{Country, CountryTaxClass, Currency, TaxClass};

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

it('returns pivot on countries when listing tax classes with include countries', function () {
    $prefix = config('venditio.routes.api.v1.prefix');
    $currency = Currency::query()->firstOrFail();

    $country = Country::query()->create([
        'name' => 'Testland',
        'iso_2' => 'TL',
        'iso_3' => 'TLS',
        'phone_code' => '+1',
        'currency_id' => $currency->getKey(),
        'flag_emoji' => 'tl',
        'capital' => 'Test City',
        'native' => 'Testland',
    ]);

    $taxClass = TaxClass::factory()->create();

    CountryTaxClass::query()->create([
        'country_id' => $country->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'rate' => 22.5,
    ]);

    $response = getJson($prefix . '/tax_classes?include[]=countries')->assertOk();

    $taxClassPayload = collect($response->json('data'))->firstWhere('id', $taxClass->getKey());
    expect($taxClassPayload)->not->toBeNull();

    $countryPayload = collect($taxClassPayload['countries'] ?? [])->firstWhere('id', $country->getKey());
    expect($countryPayload)->not->toBeNull()
        ->and($countryPayload)->toHaveKey('pivot')
        ->and((float) $countryPayload['pivot']['rate'])->toEqual(22.5)
        ->and($countryPayload['pivot']['tax_class_id'])->toBe($taxClass->getKey())
        ->and($countryPayload['pivot']['country_id'])->toBe($country->getKey());
});
