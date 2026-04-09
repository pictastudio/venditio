<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Models\{Country, CountryTaxClass, Currency, TaxClass};

use function Pest\Laravel\{assertDatabaseHas, patchJson, postJson};

uses(RefreshDatabase::class);

function createCountryForCountryTaxClassTest(array $attributes = []): Country
{
    $currency = Currency::query()->firstOrFail();

    return Country::query()->create([
        'name' => fake()->country(),
        'iso_2' => mb_strtoupper(fake()->unique()->lexify('??')),
        'iso_3' => mb_strtoupper(fake()->unique()->lexify('???')),
        'phone_code' => '+' . fake()->numberBetween(1, 999),
        'currency_id' => $currency->getKey(),
        'flag_emoji' => mb_strtolower(fake()->lexify('??')),
        'capital' => fake()->city(),
        'native' => fake()->country(),
        ...$attributes,
    ]);
}

it('bulk upserts country tax classes by updating existing rows and creating new ones', function () {
    $prefix = config('venditio.routes.api.v1.prefix');

    $firstCountry = createCountryForCountryTaxClassTest();
    $secondCountry = createCountryForCountryTaxClassTest();
    $firstTaxClass = TaxClass::factory()->create();

    $existingCountryTaxClass = CountryTaxClass::query()->create([
        'country_id' => $firstCountry->getKey(),
        'tax_class_id' => $firstTaxClass->getKey(),
        'rate' => 22,
    ]);

    postJson($prefix . '/country_tax_classes/bulk/upsert', [
        [
            'country_id' => $firstCountry->getKey(),
            'tax_class_id' => $firstTaxClass->getKey(),
            'rate' => 10,
        ],
        [
            'country_id' => $secondCountry->getKey(),
            'tax_class_id' => $firstTaxClass->getKey(),
            'rate' => 4,
        ],
    ])->assertOk()
        ->assertJsonCount(2)
        ->assertJsonFragment([
            'id' => $existingCountryTaxClass->getKey(),
            'country_id' => $firstCountry->getKey(),
            'tax_class_id' => $firstTaxClass->getKey(),
            'rate' => 10,
        ])
        ->assertJsonFragment([
            'country_id' => $secondCountry->getKey(),
            'tax_class_id' => $firstTaxClass->getKey(),
            'rate' => 4,
        ]);

    assertDatabaseHas('country_tax_class', [
        'id' => $existingCountryTaxClass->getKey(),
        'country_id' => $firstCountry->getKey(),
        'tax_class_id' => $firstTaxClass->getKey(),
        'rate' => 10,
    ]);

    assertDatabaseHas('country_tax_class', [
        'country_id' => $secondCountry->getKey(),
        'tax_class_id' => $firstTaxClass->getKey(),
        'rate' => 4,
    ]);
});

it('validates duplicate country and tax class tuples in country tax class bulk upserts', function () {
    $prefix = config('venditio.routes.api.v1.prefix');

    $country = createCountryForCountryTaxClassTest();
    $firstTaxClass = TaxClass::factory()->create();

    postJson($prefix . '/country_tax_classes/bulk/upsert', [
        'country_tax_classes' => [
            [
                'country_id' => $country->getKey(),
                'tax_class_id' => $firstTaxClass->getKey(),
                'rate' => 22,
            ],
            [
                'country_id' => $country->getKey(),
                'tax_class_id' => $firstTaxClass->getKey(),
                'rate' => 10,
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['country_tax_classes.1.tax_class_id']);
});

it('prevents creating duplicate country tax class tuples', function () {
    $prefix = config('venditio.routes.api.v1.prefix');

    $country = createCountryForCountryTaxClassTest();
    $taxClass = TaxClass::factory()->create();

    CountryTaxClass::query()->create([
        'country_id' => $country->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'rate' => 22,
    ]);

    postJson($prefix . '/country_tax_classes', [
        'country_id' => $country->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'rate' => 10,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['tax_class_id']);
});

it('prevents updating country tax classes to a duplicate country and tax class tuple', function () {
    $prefix = config('venditio.routes.api.v1.prefix');

    $firstCountry = createCountryForCountryTaxClassTest();
    $secondCountry = createCountryForCountryTaxClassTest();
    $taxClass = TaxClass::factory()->create();

    CountryTaxClass::query()->create([
        'country_id' => $firstCountry->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'rate' => 22,
    ]);

    $countryTaxClassToUpdate = CountryTaxClass::query()->create([
        'country_id' => $secondCountry->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'rate' => 10,
    ]);

    patchJson($prefix . '/country_tax_classes/' . $countryTaxClassToUpdate->getKey(), [
        'country_id' => $firstCountry->getKey(),
        'tax_class_id' => $taxClass->getKey(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['tax_class_id']);
});
