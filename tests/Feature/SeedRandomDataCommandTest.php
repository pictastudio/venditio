<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Models\{Brand, Cart, CartLine, Discount, Order, OrderLine, PriceList, PriceListPrice, Product, ProductCategory, ProductType};

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

it('seeds random venditio data from command options', function () {
    config()->set('venditio.price_lists.enabled', true);

    artisan('venditio:seed-random', [
        '--users' => 2,
        '--brands' => 3,
        '--categories' => 4,
        '--product-types' => 2,
        '--products' => 8,
        '--product-variants' => 2,
        '--options-per-variant' => 3,
        '--discounts' => 4,
        '--carts' => 2,
        '--cart-lines' => 2,
        '--orders' => 2,
        '--order-lines' => 2,
        '--price-lists' => 1,
    ])->assertSuccessful();

    expect(Brand::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(ProductCategory::query()->count())->toBeGreaterThanOrEqual(4)
        ->and(ProductType::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(Product::query()->count())->toBeGreaterThanOrEqual(8)
        ->and(Discount::withoutGlobalScopes()->count())->toBeGreaterThanOrEqual(4)
        ->and(Cart::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(CartLine::query()->count())->toBeGreaterThan(0)
        ->and(Order::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(OrderLine::query()->count())->toBeGreaterThan(0)
        ->and(PriceList::query()->count())->toBeGreaterThanOrEqual(1)
        ->and(PriceListPrice::query()->count())->toBeGreaterThan(0);
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
    config()->set('venditio.price_lists.enabled', false);

    artisan('venditio:seed-random', [
        '--products' => 5,
        '--price-lists' => 2,
    ])->assertSuccessful();

    expect(PriceList::query()->count())->toBe(0)
        ->and(PriceListPrice::query()->count())->toBe(0)
        ->and(Product::query()->count())->toBeGreaterThanOrEqual(5);
});
