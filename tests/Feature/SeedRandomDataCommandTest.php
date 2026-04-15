<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{Brand, Cart, CartLine, CreditNote, Discount, Invoice, Order, OrderLine, PriceList, PriceListPrice, Product, ProductCategory, ProductType, ReturnRequest, ShippingMethod, ShippingMethodZone, ShippingStatus, ShippingZone};

use function Pest\Laravel\artisan;

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

it('seeds random venditio data from command options with shipping snapshots and invoice-ready addresses', function () {
    config()->set('venditio.commands.seed_random_data.enabled', true);
    config()->set('venditio.price_lists.enabled', true);
    config()->set('venditio.shipping.strategy', 'zones');
    config()->set('venditio.invoices.enabled', true);
    config()->set('venditio.credit_notes.enabled', true);
    config()->set('venditio.invoices.seller', [
        'name' => 'Venditio SRL',
        'address_line_1' => 'Via Sicilia 76',
        'city' => 'Verona',
        'postal_code' => '37138',
        'country' => 'Italy',
    ]);

    artisan('venditio:seed-random', [
        '--users' => 2,
        '--brands' => 3,
        '--categories' => 4,
        '--product-types' => 2,
        '--products' => 8,
        '--product-variants' => 2,
        '--options-per-variant' => 3,
        '--discounts' => 4,
        '--shipping-statuses' => 2,
        '--shipping-methods' => 2,
        '--shipping-zones' => 2,
        '--carts' => 2,
        '--cart-lines' => 2,
        '--orders' => 2,
        '--order-lines' => 2,
        '--invoices' => 1,
        '--credit-notes' => 1,
        '--price-lists' => 1,
    ])
        ->expectsOutputToContain('Shipping Statuses')
        ->expectsOutputToContain('Shipping Method Zones')
        ->expectsOutputToContain('Invoices')
        ->expectsOutputToContain('Return Requests')
        ->expectsOutputToContain('Credit Notes')
        ->assertSuccessful();

    expect(Brand::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(ProductCategory::query()->count())->toBeGreaterThanOrEqual(4)
        ->and(ProductType::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(Product::withoutGlobalScopes()->count())->toBeGreaterThanOrEqual(8)
        ->and(Discount::withoutGlobalScopes()->count())->toBeGreaterThanOrEqual(4)
        ->and(ShippingStatus::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(ShippingMethod::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(ShippingZone::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(ShippingMethodZone::query()->count())->toBeGreaterThan(0)
        ->and(Cart::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(CartLine::query()->count())->toBeGreaterThan(0)
        ->and(Order::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(OrderLine::query()->count())->toBeGreaterThan(0)
        ->and(Invoice::query()->count())->toBeGreaterThanOrEqual(1)
        ->and(ReturnRequest::query()->count())->toBeGreaterThanOrEqual(1)
        ->and(CreditNote::query()->count())->toBeGreaterThanOrEqual(1)
        ->and(PriceList::query()->count())->toBeGreaterThanOrEqual(1)
        ->and(PriceListPrice::query()->count())->toBeGreaterThan(0);

    $brand = Brand::query()->firstOrFail();
    $category = ProductCategory::query()->firstOrFail();
    $product = Product::withoutGlobalScopes()->with('inventory')->firstOrFail();
    $cart = Cart::query()->firstOrFail();
    $order = Order::query()->firstOrFail();

    expect(data_get($brand->images, '0.type'))->toBe('thumb')
        ->and(data_get($brand->images, '0.sort_order'))->toBe(10)
        ->and(data_get($category->images, '1.type'))->toBe('cover')
        ->and(data_get($product->images, '0.thumbnail'))->toBeTrue()
        ->and(data_get($product->images, '0.active'))->toBeTrue()
        ->and(data_get($product->images, '0.sort_order'))->toBe(10)
        ->and(data_get($product->files, '0.active'))->toBeTrue()
        ->and($product->inventory?->manage_stock)->toBeBool();

    expect(data_get($cart->addresses, 'billing.country_id'))->not->toBeNull()
        ->and(data_get($cart->addresses, 'billing.state'))->not->toBeEmpty()
        ->and(data_get($cart->addresses, 'billing.zip'))->not->toBeEmpty()
        ->and(data_get($cart->addresses, 'billing.sdi'))->not->toBeEmpty()
        ->and(data_get($cart->addresses, 'billing.pec'))->not->toBeEmpty()
        ->and(data_get($cart->addresses, 'shipping.country_id'))->not->toBeNull()
        ->and(data_get($cart->addresses, 'shipping.province_id'))->not->toBeNull()
        ->and((float) $cart->specific_weight)->toBeGreaterThanOrEqual(0)
        ->and((float) $cart->chargeable_weight)->toBeGreaterThanOrEqual(0);

    expect(data_get($order->addresses, 'billing.country_id'))->not->toBeNull()
        ->and(data_get($order->addresses, 'billing.country'))->not->toBeEmpty()
        ->and(data_get($order->addresses, 'billing.state'))->not->toBeEmpty()
        ->and(data_get($order->addresses, 'billing.zip'))->not->toBeEmpty()
        ->and(data_get($order->addresses, 'billing.sdi'))->not->toBeEmpty()
        ->and(data_get($order->addresses, 'billing.pec'))->not->toBeEmpty()
        ->and(data_get($order->addresses, 'shipping.country_id'))->not->toBeNull()
        ->and($order->shipping_method_data)->not->toBeNull()
        ->and($order->shipping_zone_data)->not->toBeNull();
});

it('does nothing when random seed command is disabled', function () {
    config()->set('venditio.commands.seed_random_data.enabled', false);

    artisan('venditio:seed-random', [
        '--brands' => 3,
        '--products' => 5,
        '--carts' => 2,
    ])->assertSuccessful();

    expect(Brand::query()->count())->toBe(0)
        ->and(Product::query()->count())->toBe(0)
        ->and(Cart::query()->count())->toBe(0);
});

it('skips price list seeding when price lists are disabled', function () {
    config()->set('venditio.commands.seed_random_data.enabled', true);
    config()->set('venditio.price_lists.enabled', false);

    artisan('venditio:seed-random', [
        '--products' => 5,
        '--price-lists' => 2,
    ])->assertSuccessful();

    expect(PriceList::query()->count())->toBe(0)
        ->and(PriceListPrice::query()->count())->toBe(0)
        ->and(Product::withoutGlobalScopes()->count())->toBeGreaterThanOrEqual(5);
});

it('skips invoice seeding when invoices are disabled', function () {
    config()->set('venditio.commands.seed_random_data.enabled', true);
    config()->set('venditio.shipping.strategy', 'zones');
    config()->set('venditio.invoices.enabled', false);

    artisan('venditio:seed-random', [
        '--products' => 4,
        '--shipping-statuses' => 1,
        '--shipping-methods' => 1,
        '--shipping-zones' => 1,
        '--orders' => 2,
        '--order-lines' => 2,
        '--invoices' => 2,
    ])
        ->expectsOutputToContain('Skipping invoice seeding')
        ->assertSuccessful();

    expect(Order::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(Invoice::query()->count())->toBe(0);
});

it('skips invoice seeding when seller configuration is incomplete', function () {
    config()->set('venditio.commands.seed_random_data.enabled', true);
    config()->set('venditio.shipping.strategy', 'zones');
    config()->set('venditio.invoices.enabled', true);
    config()->set('venditio.invoices.seller', []);

    artisan('venditio:seed-random', [
        '--products' => 4,
        '--shipping-statuses' => 1,
        '--shipping-methods' => 1,
        '--shipping-zones' => 1,
        '--orders' => 1,
        '--order-lines' => 1,
        '--invoices' => 1,
    ])
        ->expectsOutputToContain('Invoice seller configuration is incomplete')
        ->assertSuccessful();

    expect(Order::query()->count())->toBeGreaterThanOrEqual(1)
        ->and(Invoice::query()->count())->toBe(0);
});

it('skips credit note seeding when credit notes are disabled', function () {
    config()->set('venditio.commands.seed_random_data.enabled', true);
    config()->set('venditio.shipping.strategy', 'zones');
    config()->set('venditio.invoices.enabled', true);
    config()->set('venditio.credit_notes.enabled', false);
    config()->set('venditio.invoices.seller', [
        'name' => 'Venditio SRL',
        'address_line_1' => 'Via Sicilia 76',
        'city' => 'Verona',
        'postal_code' => '37138',
        'country' => 'Italy',
    ]);

    artisan('venditio:seed-random', [
        '--products' => 4,
        '--shipping-statuses' => 1,
        '--shipping-methods' => 1,
        '--shipping-zones' => 1,
        '--orders' => 2,
        '--order-lines' => 2,
        '--credit-notes' => 1,
    ])
        ->expectsOutputToContain('Skipping credit note seeding')
        ->assertSuccessful();

    expect(Order::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(CreditNote::query()->count())->toBe(0);
});

it('skips credit note seeding when invoices are disabled', function () {
    config()->set('venditio.commands.seed_random_data.enabled', true);
    config()->set('venditio.shipping.strategy', 'zones');
    config()->set('venditio.invoices.enabled', false);
    config()->set('venditio.credit_notes.enabled', true);

    artisan('venditio:seed-random', [
        '--products' => 4,
        '--shipping-statuses' => 1,
        '--shipping-methods' => 1,
        '--shipping-zones' => 1,
        '--orders' => 2,
        '--order-lines' => 2,
        '--credit-notes' => 1,
    ])
        ->expectsOutputToContain('Credit note seeding requires invoices')
        ->assertSuccessful();

    expect(Order::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(Invoice::query()->count())->toBe(0)
        ->and(CreditNote::query()->count())->toBe(0);
});
