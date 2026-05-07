<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Event, Schema};
use PictaStudio\Venditio\Dto\{CartDto, OrderDto};
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Events\{ProductOutOfStock, ProductStockBelowMinimum};
use PictaStudio\Venditio\Models\{Country, CountryTaxClass, Currency, Product, TaxClass, User};
use PictaStudio\Venditio\Pipelines\Cart\CartCreationPipeline;
use PictaStudio\Venditio\Pipelines\Order\OrderCreationPipeline;

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

function setupStockTaxEnvironment(TaxClass $taxClass): void
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

function createStockProduct(TaxClass $taxClass, int $stock, int $stockMin = 0, bool $manageStock = true): Product
{
    $product = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    $product->inventory()->updateOrCreate([], [
        'stock' => $stock,
        'stock_reserved' => 0,
        'stock_available' => $stock,
        'stock_min' => $stockMin,
        'manage_stock' => $manageStock,
        'price' => 100,
        'price_includes_tax' => false,
        'purchase_price' => null,
    ]);

    return $product->refresh();
}

it('keeps `stock_available` synced with `stock - stock_reserved`', function () {
    $taxClass = TaxClass::factory()->create();
    setupStockTaxEnvironment($taxClass);

    $product = createStockProduct($taxClass, 10, 2);
    $inventory = $product->inventory()->firstOrFail();

    $inventory->update([
        'stock' => 15,
        'stock_reserved' => 4,
        'stock_available' => 999,
    ]);

    $inventory->refresh();

    expect($inventory->stock_available)->toBe(11)
        ->and($inventory->stock_available)->toBe($inventory->stock - $inventory->stock_reserved);
});

it('reserves stock on cart creation and commits stock on order creation for multiple products', function () {
    $taxClass = TaxClass::factory()->create();
    setupStockTaxEnvironment($taxClass);

    $user = User::query()->create([
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => 'mario-stock@example.test',
        'phone' => '123456789',
    ]);

    $productA = createStockProduct($taxClass, 20, 3);
    $productB = createStockProduct($taxClass, 15, 2);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'user_id' => $user->getKey(),
            'user_first_name' => $user->first_name,
            'user_last_name' => $user->last_name,
            'user_email' => $user->email,
            'lines' => [
                ['product_id' => $productA->getKey(), 'qty' => 4],
                ['product_id' => $productB->getKey(), 'qty' => 5],
            ],
        ])
    )->load('lines');

    $productA->inventory->refresh();
    $productB->inventory->refresh();

    expect($productA->inventory->stock)->toBe(20)
        ->and($productA->inventory->stock_reserved)->toBe(4)
        ->and($productA->inventory->stock_available)->toBe(16)
        ->and($productB->inventory->stock)->toBe(15)
        ->and($productB->inventory->stock_reserved)->toBe(5)
        ->and($productB->inventory->stock_available)->toBe(10);

    $order = OrderCreationPipeline::make()->run(OrderDto::fromCart($cart));

    $productA->inventory->refresh();
    $productB->inventory->refresh();
    $cart->refresh();

    expect($order->getKey())->not->toBeNull()
        ->and($productA->inventory->stock)->toBe(16)
        ->and($productA->inventory->stock_reserved)->toBe(0)
        ->and($productA->inventory->stock_available)->toBe(16)
        ->and($productB->inventory->stock)->toBe(10)
        ->and($productB->inventory->stock_reserved)->toBe(0)
        ->and($productB->inventory->stock_available)->toBe(10)
        ->and($cart->order_id)->toBe($order->getKey())
        ->and($cart->status->value)->toBe(config('venditio.cart.status_enum')::getConvertedStatus()->value);
});

it('skips stock validation and movements when inventory stock management is disabled', function () {
    $taxClass = TaxClass::factory()->create();
    setupStockTaxEnvironment($taxClass);

    $user = User::query()->create([
        'first_name' => 'Giulia',
        'last_name' => 'Verdi',
        'email' => 'giulia-stock-disabled@example.test',
        'phone' => '123456789',
    ]);

    $product = createStockProduct($taxClass, 2, 0, false);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'user_id' => $user->getKey(),
            'user_first_name' => $user->first_name,
            'user_last_name' => $user->last_name,
            'user_email' => $user->email,
            'lines' => [
                ['product_id' => $product->getKey(), 'qty' => 5],
            ],
        ])
    );

    $product->inventory->refresh();

    expect($product->inventory->manage_stock)->toBeFalse()
        ->and($product->inventory->stock)->toBe(2)
        ->and($product->inventory->stock_reserved)->toBe(0)
        ->and($product->inventory->stock_available)->toBe(2);

    OrderCreationPipeline::make()->run(OrderDto::fromCart($cart));

    $product->inventory->refresh();

    expect($product->inventory->stock)->toBe(2)
        ->and($product->inventory->stock_reserved)->toBe(0)
        ->and($product->inventory->stock_available)->toBe(2);
});

it('allows cart creation for out-of-stock products when inventory stock management is disabled', function () {
    $taxClass = TaxClass::factory()->create();
    setupStockTaxEnvironment($taxClass);

    $user = User::query()->create([
        'first_name' => 'Anna',
        'last_name' => 'Neri',
        'email' => 'anna-stock-disabled-zero@example.test',
        'phone' => '123456789',
    ]);

    $product = createStockProduct($taxClass, 0, 0, false);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'user_id' => $user->getKey(),
            'user_first_name' => $user->first_name,
            'user_last_name' => $user->last_name,
            'user_email' => $user->email,
            'lines' => [
                ['product_id' => $product->getKey(), 'qty' => 5],
            ],
        ])
    )->load('lines');

    $product->inventory->refresh();

    expect($cart->getKey())->not->toBeNull()
        ->and($cart->lines)->toHaveCount(1)
        ->and($cart->lines->first()->qty)->toBe(5)
        ->and($product->inventory->manage_stock)->toBeFalse()
        ->and($product->inventory->stock)->toBe(0)
        ->and($product->inventory->stock_reserved)->toBe(0)
        ->and($product->inventory->stock_available)->toBe(0);
});

it('dispatches a `low stock` event with the expected data when `stock` goes below `stock_min`', function () {
    Event::fake([ProductStockBelowMinimum::class]);

    $taxClass = TaxClass::factory()->create();
    setupStockTaxEnvironment($taxClass);

    $user = User::query()->create([
        'first_name' => 'Luca',
        'last_name' => 'Bianchi',
        'email' => 'luca-stock@example.test',
        'phone' => '123456789',
    ]);

    $product = createStockProduct($taxClass, 10, 8);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'user_id' => $user->getKey(),
            'user_first_name' => $user->first_name,
            'user_last_name' => $user->last_name,
            'user_email' => $user->email,
            'lines' => [
                ['product_id' => $product->getKey(), 'qty' => 3],
            ],
        ])
    );

    OrderCreationPipeline::make()->run(OrderDto::fromCart($cart));

    Event::assertDispatched(ProductStockBelowMinimum::class, function (ProductStockBelowMinimum $event) use ($product) {
        return $event->product->getKey() === $product->getKey()
            && $event->stock === 7
            && $event->stock_reserved === 0
            && $event->stock_available === 7
            && $event->stock_min === 8;
    });
});

it('dispatches an `out of stock` event with the expected data when `stock` reaches zero', function () {
    Event::fake([ProductOutOfStock::class]);

    $taxClass = TaxClass::factory()->create();
    setupStockTaxEnvironment($taxClass);

    $user = User::query()->create([
        'first_name' => 'Sara',
        'last_name' => 'Neri',
        'email' => 'sara-out-of-stock@example.test',
        'phone' => '123456789',
    ]);

    $product = createStockProduct($taxClass, 3, 1);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'user_id' => $user->getKey(),
            'user_first_name' => $user->first_name,
            'user_last_name' => $user->last_name,
            'user_email' => $user->email,
            'lines' => [
                ['product_id' => $product->getKey(), 'qty' => 3],
            ],
        ])
    );

    OrderCreationPipeline::make()->run(OrderDto::fromCart($cart));

    Event::assertDispatched(ProductOutOfStock::class, function (ProductOutOfStock $event) use ($product) {
        return $event->product->getKey() === $product->getKey()
            && $event->stock === 0
            && $event->stock_reserved === 0
            && $event->stock_available === 0
            && $event->stock_min === 1;
    });
});
