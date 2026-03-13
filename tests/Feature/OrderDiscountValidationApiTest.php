<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Enums\{DiscountType, ProductStatus};
use PictaStudio\Venditio\Models\{Country, CountryTaxClass, Currency, TaxClass, User};

use function Pest\Laravel\{patchJson, postJson};

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

function setupOrderDiscountValidationTaxEnvironment(TaxClass $taxClass): void
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

function createOrderDiscountValidationUser(string $email): User
{
    return User::query()->create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $email,
        'phone' => '123456789',
    ]);
}

function createOrderDiscountValidationProduct(TaxClass $taxClass): PictaStudio\Venditio\Models\Product
{
    $product = config('venditio.models.product')::query()->create([
        'tax_class_id' => $taxClass->getKey(),
        'name' => 'Order Discount Product',
        'slug' => 'order-discount-product',
        'status' => ProductStatus::Published,
        'active' => true,
        'new' => true,
        'in_evidence' => true,
        'sku' => 'ORDER-DISCOUNT-VALIDATION-001',
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    $product->inventory()->updateOrCreate([], [
        'stock' => 100,
        'stock_reserved' => 0,
        'stock_available' => 100,
        'stock_min' => 0,
        'price' => 100,
        'price_includes_tax' => false,
        'purchase_price' => null,
    ]);

    return $product->refresh();
}

it('returns 422 when creating an order from a cart with a non eligible cart total discount code', function () {
    $taxClass = TaxClass::factory()->create();
    setupOrderDiscountValidationTaxEnvironment($taxClass);
    $user = createOrderDiscountValidationUser('user-invalid-order-discount@example.test');
    $product = createOrderDiscountValidationProduct($taxClass);

    $prefix = config('venditio.routes.api.v1.prefix');

    $discountModel = config('venditio.models.discount');
    $discountModel::query()->create([
        'discountable_type' => null,
        'discountable_id' => null,
        'type' => DiscountType::Percentage,
        'value' => 10,
        'code' => 'TEST10',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $cartId = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()->json('id');

    $cartModel = config('venditio.models.cart');
    $cart = $cartModel::query()->findOrFail($cartId);
    $cart->discount_code = 'TEST10';
    $cart->save();

    postJson($prefix . '/orders', [
        'cart_id' => $cartId,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.discount_code.0', 'The discount code [TEST10] is invalid or not eligible for cart total discounts.');
});

it('copies inventory currency_id from cart lines to order lines', function () {
    $taxClass = TaxClass::factory()->create();
    setupOrderDiscountValidationTaxEnvironment($taxClass);
    $user = createOrderDiscountValidationUser('user-order-line-currency@example.test');

    $lineCurrencyId = Currency::query()->firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'USD', 'exchange_rate' => 1, 'is_enabled' => true, 'is_default' => false]
    )->getKey();

    $product = createOrderDiscountValidationProduct($taxClass);
    $product->inventory()->updateOrCreate([], ['currency_id' => $lineCurrencyId]);
    $product = $product->refresh();

    $prefix = config('venditio.routes.api.v1.prefix');

    $cartId = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()->json('id');

    $response = postJson($prefix . '/orders', [
        'cart_id' => $cartId,
    ])->assertOk();

    $response->assertJsonPath('lines.0.currency_id', $lineCurrencyId);

    $orderLineModel = config('venditio.models.order_line');

    expect((int) $orderLineModel::query()
        ->where('order_id', (int) $response->json('id'))
        ->value('currency_id'))
        ->toBe($lineCurrencyId);
});

it('accepts sdi and pec when updating order addresses', function () {
    $taxClass = TaxClass::factory()->create();
    setupOrderDiscountValidationTaxEnvironment($taxClass);
    $user = createOrderDiscountValidationUser('user-order-address-fields@example.test');
    $product = createOrderDiscountValidationProduct($taxClass);

    $prefix = config('venditio.routes.api.v1.prefix');

    $cartId = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()->json('id');

    $orderId = postJson($prefix . '/orders', [
        'cart_id' => $cartId,
    ])->assertOk()->json('id');

    patchJson($prefix . '/orders/' . $orderId, [
        'addresses' => [
            'billing' => [
                'sdi' => 'ABC1234',
                'pec' => 'billing@pec.example.test',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('addresses.billing.sdi', 'ABC1234')
        ->assertJsonPath('addresses.billing.pec', 'billing@pec.example.test');
});

it('rejects invalid pec when updating order addresses', function () {
    $taxClass = TaxClass::factory()->create();
    setupOrderDiscountValidationTaxEnvironment($taxClass);
    $user = createOrderDiscountValidationUser('user-order-address-invalid-pec@example.test');
    $product = createOrderDiscountValidationProduct($taxClass);

    $prefix = config('venditio.routes.api.v1.prefix');

    $cartId = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()->json('id');

    $orderId = postJson($prefix . '/orders', [
        'cart_id' => $cartId,
    ])->assertOk()->json('id');

    patchJson($prefix . '/orders/' . $orderId, [
        'addresses' => [
            'billing' => [
                'pec' => 'invalid-pec',
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['addresses.billing.pec']);
});
