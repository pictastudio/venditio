<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Currency, Product, TaxClass};

uses(RefreshDatabase::class);

function createCurrency(array $overrides = []): Currency
{
    static $index = 0;
    $index++;

    $defaults = [
        'name' => 'Currency ' . $index,
        'code' => 'C' . mb_str_pad((string) $index, 2, '0', STR_PAD_LEFT),
        'symbol' => null,
        'exchange_rate' => 1,
        'decimal_places' => 2,
        'is_enabled' => true,
        'is_default' => false,
    ];

    return Currency::query()->create(array_merge($defaults, $overrides));
}

it('keeps only one default currency at a time', function () {
    $first = createCurrency([
        'name' => 'Pound Sterling',
        'code' => 'GBP',
        'is_default' => true,
    ]);

    $second = createCurrency([
        'name' => 'US Dollar',
        'code' => 'USD',
        'is_default' => true,
    ]);

    expect($first->refresh()->is_default)->toBeFalse()
        ->and($second->refresh()->is_default)->toBeTrue()
        ->and(Currency::query()->where('is_default', true)->count())->toBe(1);
});

it('assigns default currency to product inventory when currency_id is missing', function () {
    $defaultCurrency = createCurrency([
        'name' => 'Pound Sterling',
        'code' => 'GBP',
        'is_default' => true,
    ]);

    $taxClass = TaxClass::factory()->create();

    $product = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    expect($product->refresh()->inventory?->currency_id)->toBe($defaultCurrency->getKey());
});
