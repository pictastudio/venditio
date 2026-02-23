<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Dto\CartDto;
use PictaStudio\Venditio\Enums\ProductStatus;
use PictaStudio\Venditio\Models\{Cart, Country, CountryTaxClass, Currency, Product, TaxClass, User};
use PictaStudio\Venditio\Pipelines\Cart\CartCreationPipeline;

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

function setupAbandonedCartTaxEnvironment(TaxClass $taxClass): void
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

function createAbandonedCartProduct(TaxClass $taxClass, int $stock): Product
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
        'stock_min' => 0,
        'price' => 100,
        'price_includes_tax' => false,
        'purchase_price' => null,
    ]);

    return $product->refresh();
}

function createCartForAbandonmentTest(User $user, Product $product, int $qty): Cart
{
    return CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'user_id' => $user->getKey(),
            'user_first_name' => $user->first_name,
            'user_last_name' => $user->last_name,
            'user_email' => $user->email,
            'lines' => [
                ['product_id' => $product->getKey(), 'qty' => $qty],
            ],
        ])
    )->load('lines');
}

it('marks stale pending carts as abandoned and releases reserved stock', function () {
    config()->set('venditio.commands.release_stock_for_abandoned_carts.enabled', true);
    config()->set('venditio.commands.release_stock_for_abandoned_carts.inactive_for_minutes', 1_440);

    $taxClass = TaxClass::factory()->create();
    setupAbandonedCartTaxEnvironment($taxClass);

    $user = User::query()->create([
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => 'abandoned-cart@example.test',
        'phone' => '123456789',
    ]);

    $product = createAbandonedCartProduct($taxClass, 20);
    $cart = createCartForAbandonmentTest($user, $product, 4);
    $cart->forceFill(['updated_at' => now()->subDays(2)])->saveQuietly();

    expect($product->inventory->refresh()->stock_reserved)->toBe(4);

    artisan('carts:update-abandoned')->assertSuccessful();

    $cart->refresh();
    $product->inventory->refresh();

    expect($cart->status->value)->toBe(config('venditio.cart.status_enum')::getAbandonedStatus()->value)
        ->and($cart->trashed())->toBeFalse()
        ->and($cart->lines()->count())->toBe(1)
        ->and($product->inventory->stock_reserved)->toBe(0);
});

it('does not abandon carts updated inside the configured inactivity window', function () {
    config()->set('venditio.commands.release_stock_for_abandoned_carts.enabled', true);
    config()->set('venditio.commands.release_stock_for_abandoned_carts.inactive_for_minutes', 1_440);

    $taxClass = TaxClass::factory()->create();
    setupAbandonedCartTaxEnvironment($taxClass);

    $user = User::query()->create([
        'first_name' => 'Luca',
        'last_name' => 'Bianchi',
        'email' => 'active-cart@example.test',
        'phone' => '123456789',
    ]);

    $product = createAbandonedCartProduct($taxClass, 20);
    $cart = createCartForAbandonmentTest($user, $product, 3);
    $initialStatus = $cart->status->value;
    $cart->forceFill(['updated_at' => now()->subHours(6)])->saveQuietly();

    artisan('carts:update-abandoned')->assertSuccessful();

    $cart->refresh();
    $product->inventory->refresh();

    expect($cart->status->value)->toBe($initialStatus)
        ->and($product->inventory->stock_reserved)->toBe(3);
});

it('does nothing when abandoned carts command is disabled', function () {
    config()->set('venditio.commands.release_stock_for_abandoned_carts.enabled', false);
    config()->set('venditio.commands.release_stock_for_abandoned_carts.inactive_for_minutes', 1_440);

    $taxClass = TaxClass::factory()->create();
    setupAbandonedCartTaxEnvironment($taxClass);

    $user = User::query()->create([
        'first_name' => 'Anna',
        'last_name' => 'Verdi',
        'email' => 'disabled-command@example.test',
        'phone' => '123456789',
    ]);

    $product = createAbandonedCartProduct($taxClass, 20);
    $cart = createCartForAbandonmentTest($user, $product, 2);
    $initialStatus = $cart->status->value;
    $cart->forceFill(['updated_at' => now()->subDays(2)])->saveQuietly();

    artisan('carts:update-abandoned')->assertSuccessful();

    $cart->refresh();
    $product->inventory->refresh();

    expect($cart->status->value)->toBe($initialStatus)
        ->and($product->inventory->stock_reserved)->toBe(2);
});

it('registers abandoned carts command in scheduler only when enabled', function () {
    config()->set('venditio.commands.release_stock_for_abandoned_carts.enabled', true);
    config()->set('venditio.commands.release_stock_for_abandoned_carts.schedule_every_minutes', 15);

    app()->forgetInstance(Schedule::class);
    $enabledSchedule = app(Schedule::class);

    $enabledEvent = collect($enabledSchedule->events())->first(
        fn (object $event): bool => str_contains($event->command, 'carts:update-abandoned')
    );

    expect($enabledEvent)->not->toBeNull();
});

it('does not register abandoned carts command in scheduler when disabled', function () {
    config()->set('venditio.commands.release_stock_for_abandoned_carts.enabled', false);

    app()->forgetInstance(Schedule::class);
    $disabledSchedule = app(Schedule::class);

    $disabledEvent = collect($disabledSchedule->events())->first(
        fn (object $event): bool => str_contains($event->command, 'carts:update-abandoned')
    );

    expect($disabledEvent)->toBeNull();
});
