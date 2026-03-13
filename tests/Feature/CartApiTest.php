<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Dto\CartDto;
use PictaStudio\Venditio\Enums\{DiscountType, ProductStatus};
use PictaStudio\Venditio\Models\{Cart, Country, CountryTaxClass, Currency, Product, TaxClass, User};
use PictaStudio\Venditio\Pipelines\Cart\CartCreationPipeline;

use function Pest\Laravel\{assertSoftDeleted, deleteJson, getJson, patchJson, postJson};

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

function setupCartTaxEnvironment(TaxClass $taxClass): void
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

function createCartProduct(TaxClass $taxClass, float $price = 100): Product
{
    $product = Product::factory()->create([
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);

    $product->inventory()->updateOrCreate([], [
        'stock' => 100,
        'stock_reserved' => 0,
        'stock_available' => 100,
        'stock_min' => 0,
        'price' => $price,
        'price_includes_tax' => false,
        'purchase_price' => null,
    ]);

    return $product->refresh();
}

function createUserForCart(string $email): User
{
    return User::query()->create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $email,
        'phone' => '123456789',
    ]);
}

it('creates a cart through api with lines', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass);
    $user = createUserForCart('user-create-cart@example.test');

    $prefix = config('venditio.routes.api.v1.prefix');

    $response = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 2],
        ],
    ])->assertCreated();

    $cartId = $response->json('id');

    expect($cartId)->not->toBeNull()
        ->and((float) $response->json('total_final'))->toBe(244.0);

    getJson($prefix . '/carts/' . $cartId)
        ->assertOk()
        ->assertJsonPath('lines.0.product_id', $product->getKey())
        ->assertJsonPath('lines.0.qty', 2);
});

it('copies inventory currency_id into cart lines', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);

    $lineCurrencyId = Currency::query()->firstOrCreate(
        ['code' => 'USD'],
        ['name' => 'USD', 'exchange_rate' => 1, 'is_enabled' => true, 'is_default' => false]
    )->getKey();

    $product = createCartProduct($taxClass);
    $product->inventory()->updateOrCreate([], ['currency_id' => $lineCurrencyId]);
    $product = $product->refresh();

    $user = createUserForCart('user-cart-line-currency@example.test');
    $prefix = config('venditio.routes.api.v1.prefix');

    $response = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated();

    $cartId = $response->json('id');

    getJson($prefix . '/carts/' . $cartId)
        ->assertOk()
        ->assertJsonPath('lines.0.currency_id', $lineCurrencyId);

    $cartLineModel = config('venditio.models.cart_line');

    expect((int) $cartLineModel::query()
        ->where('cart_id', $cartId)
        ->value('currency_id'))
        ->toBe($lineCurrencyId);
});

it('uses the billing address country tax rate when calculating cart line VAT', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass);
    $user = createUserForCart('user-country-tax@example.test');

    $currencyId = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'EUR', 'exchange_rate' => 1, 'is_enabled' => true, 'is_default' => false]
    )->getKey();

    $otherCountry = Country::query()->create([
        'name' => 'Germany',
        'iso_2' => 'DE',
        'iso_3' => 'DEU',
        'phone_code' => '+49',
        'currency_id' => $currencyId,
        'flag_emoji' => 'de',
        'capital' => 'Berlin',
        'native' => 'Deutschland',
    ]);

    CountryTaxClass::query()->create([
        'country_id' => $otherCountry->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'rate' => 10,
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    $cartId = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'addresses' => [
            'billing' => [
                'country_id' => $otherCountry->getKey(),
            ],
            'shipping' => [
                'country_id' => Country::query()->where('iso_2', 'IT')->value('id'),
            ],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()->json('id');

    getJson($prefix . '/carts/' . $cartId)
        ->assertOk()
        ->assertJsonPath('lines.0.tax_rate', 10)
        ->assertJsonPath('lines.0.unit_final_price_tax', 10)
        ->assertJsonPath('total_final', 110);
});

it('recalculates cart line VAT when the billing address country changes', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass);
    $user = createUserForCart('user-country-tax-update@example.test');

    $currencyId = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'EUR', 'exchange_rate' => 1, 'is_enabled' => true, 'is_default' => false]
    )->getKey();

    $otherCountry = Country::query()->create([
        'name' => 'Germany',
        'iso_2' => 'DE',
        'iso_3' => 'DEU',
        'phone_code' => '+49',
        'currency_id' => $currencyId,
        'flag_emoji' => 'de',
        'capital' => 'Berlin',
        'native' => 'Deutschland',
    ]);

    CountryTaxClass::query()->create([
        'country_id' => $otherCountry->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'rate' => 10,
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');
    $italyId = Country::query()->where('iso_2', 'IT')->value('id');

    $cartId = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'addresses' => [
            'billing' => [
                'country_id' => $italyId,
            ],
        ],
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()
        ->assertJsonPath('total_final', 122)
        ->json('id');

    getJson($prefix . '/carts/' . $cartId)
        ->assertOk()
        ->assertJsonPath('lines.0.tax_rate', 22)
        ->assertJsonPath('lines.0.unit_final_price_tax', 22)
        ->assertJsonPath('total_final', 122);

    patchJson($prefix . '/carts/' . $cartId, [
        'addresses' => [
            'billing' => [
                'country_id' => $otherCountry->getKey(),
            ],
        ],
    ])->assertOk();

    getJson($prefix . '/carts/' . $cartId)
        ->assertOk()
        ->assertJsonPath('lines.0.tax_rate', 10)
        ->assertJsonPath('lines.0.unit_final_price_tax', 10)
        ->assertJsonPath('total_final', 110);
});

it('recalculates cart line VAT when checkout sets billing and shipping addresses on the cart', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass);

    $currencyId = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'EUR', 'exchange_rate' => 1, 'is_enabled' => true, 'is_default' => false]
    )->getKey();

    $otherCountry = Country::query()->create([
        'name' => 'Germany',
        'iso_2' => 'DE',
        'iso_3' => 'DEU',
        'phone_code' => '+49',
        'currency_id' => $currencyId,
        'flag_emoji' => 'de',
        'capital' => 'Berlin',
        'native' => 'Deutschland',
    ]);

    CountryTaxClass::query()->create([
        'country_id' => $otherCountry->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'rate' => 10,
    ]);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'lines' => [
                ['product_id' => $product->getKey(), 'qty' => 1],
            ],
        ])
    )->load('lines');

    expect((float) $cart->lines->first()->tax_rate)->toBe(22.0)
        ->and((float) $cart->total_final)->toBe(122.0);

    $italyId = Country::query()->where('iso_2', 'IT')->value('id');
    $prefix = config('venditio.routes.api.v1.prefix');

    patchJson($prefix . '/carts/' . $cart->getKey(), [
        'addresses' => [
            'billing' => [
                'country_id' => $otherCountry->getKey(),
            ],
            'shipping' => [
                'country_id' => $italyId,
            ],
        ],
    ])->assertOk();

    getJson($prefix . '/carts/' . $cart->getKey())
        ->assertOk()
        ->assertJsonPath('lines.0.tax_rate', 10)
        ->assertJsonPath('lines.0.unit_final_price_tax', 10)
        ->assertJsonPath('total_final', 110);
});

it('accepts sdi and pec in cart addresses', function () {
    $user = createUserForCart('user-cart-address-fields@example.test');
    $prefix = config('venditio.routes.api.v1.prefix');

    $cartId = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'addresses' => [
            'billing' => [
                'sdi' => 'ABC1234',
                'pec' => 'billing@pec.example.test',
            ],
        ],
    ])->assertCreated()
        ->json('id');

    getJson($prefix . '/carts/' . $cartId)
        ->assertOk()
        ->assertJsonPath('addresses.billing.sdi', 'ABC1234')
        ->assertJsonPath('addresses.billing.pec', 'billing@pec.example.test');
});

it('rejects invalid pec in cart addresses', function () {
    $user = createUserForCart('user-cart-address-invalid-pec@example.test');
    $prefix = config('venditio.routes.api.v1.prefix');

    postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'addresses' => [
            'billing' => [
                'pec' => 'invalid-pec',
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['addresses.billing.pec']);
});

it('filters carts by user_id', function () {
    $userA = createUserForCart('user-a@example.test');
    $userB = createUserForCart('user-b@example.test');

    $prefix = config('venditio.routes.api.v1.prefix');

    $cartA = Cart::query()->create([
        'user_id' => $userA->getKey(),
        'identifier' => 'cart-user-a',
        'user_first_name' => $userA->first_name,
        'user_last_name' => $userA->last_name,
        'user_email' => $userA->email,
        'status' => config('venditio.cart.status_enum')::getActiveStatus(),
        'sub_total_taxable' => 0,
        'sub_total_tax' => 0,
        'sub_total' => 0,
        'shipping_fee' => 0,
        'payment_fee' => 0,
        'discount_amount' => 0,
        'total_final' => 0,
        'addresses' => null,
    ])->getKey();

    $cartB = Cart::query()->create([
        'user_id' => $userB->getKey(),
        'identifier' => 'cart-user-b',
        'user_first_name' => $userB->first_name,
        'user_last_name' => $userB->last_name,
        'user_email' => $userB->email,
        'status' => config('venditio.cart.status_enum')::getActiveStatus(),
        'sub_total_taxable' => 0,
        'sub_total_tax' => 0,
        'sub_total' => 0,
        'shipping_fee' => 0,
        'payment_fee' => 0,
        'discount_amount' => 0,
        'total_final' => 0,
        'addresses' => null,
    ])->getKey();

    $response = getJson($prefix . '/carts?all=1&user_id=' . $userA->getKey())
        ->assertOk();

    $json = $response->json();
    $ids = collect($json)->pluck('id')->filter()->values();

    if ($ids->isEmpty() && is_array(data_get($json, 'data'))) {
        $ids = collect(data_get($json, 'data'))->pluck('id')->filter()->values();
    }

    expect($ids)->toContain($cartA)
        ->not->toContain($cartB);
});

it('returns cart detail with lines loaded', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass);
    $user = createUserForCart('user-lines@example.test');

    $prefix = config('venditio.routes.api.v1.prefix');

    $cartId = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 2],
        ],
    ])->assertCreated()->json('id');

    getJson($prefix . '/carts/' . $cartId)
        ->assertOk()
        ->assertJsonPath('id', $cartId)
        ->assertJsonPath('lines.0.product_id', $product->getKey())
        ->assertJsonPath('lines.0.qty', 2);
});

it('deletes a cart through api', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass);
    $user = createUserForCart('user-delete@example.test');

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

    deleteJson($prefix . '/carts/' . $cartId)
        ->assertOk();

    $product->inventory->refresh();

    assertSoftDeleted('carts', ['id' => $cartId]);
    assertSoftDeleted('cart_lines', ['cart_id' => $cartId]);
    expect($product->inventory->stock_reserved)->toBe(0);
});

it('adds lines and recalculates cart totals through pipeline', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass);
    $user = createUserForCart('user-add-lines@example.test');

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

    postJson($prefix . '/carts/' . $cartId . '/add_lines', [
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 2],
        ],
    ])->assertOk()
        ->assertJsonPath('lines.0.qty', 3);

    $cart = config('venditio.models.cart')::query()->with('lines')->findOrFail($cartId);
    $product->inventory->refresh();

    expect($cart->lines)->toHaveCount(1)
        ->and($cart->lines->first()->qty)->toBe(3)
        ->and((float) $cart->total_final)->toBe(366.0)
        ->and($product->inventory->stock_reserved)->toBe(3);
});

it('adds a line for a product already in the cart and updates the existing line correctly', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass, 100);
    $user = createUserForCart('user-same-product@example.test');

    $prefix = config('venditio.routes.api.v1.prefix');

    $cartId = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 2],
        ],
    ])->assertCreated()->json('id');

    $initialTotal = (float) getJson($prefix . '/carts/' . $cartId)->json('total_final');
    expect($initialTotal)->toBe(244.0);

    $response = postJson($prefix . '/carts/' . $cartId . '/add_lines', [
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 3],
        ],
    ])->assertOk();

    $response->assertJsonPath('lines.0.product_id', $product->getKey())
        ->assertJsonPath('lines.0.qty', 5);

    $cart = config('venditio.models.cart')::query()->with('lines')->findOrFail($cartId);
    $product->inventory->refresh();

    expect($cart->lines)->toHaveCount(1)
        ->and($cart->lines->first()->product_id)->toBe($product->getKey())
        ->and($cart->lines->first()->qty)->toBe(5)
        ->and((float) $cart->total_final)->toBe(610.0)
        ->and($product->inventory->stock_reserved)->toBe(5);
});

it('removes lines and recalculates cart totals through pipeline', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass);
    $user = createUserForCart('user-remove-lines@example.test');

    $prefix = config('venditio.routes.api.v1.prefix');

    $cartId = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 2],
        ],
    ])->assertCreated()->json('id');

    $lineId = config('venditio.models.cart_line')::query()
        ->where('cart_id', $cartId)
        ->value('id');

    postJson($prefix . '/carts/' . $cartId . '/remove_lines', [
        'line_ids' => [$lineId],
    ])->assertOk();

    $cart = config('venditio.models.cart')::query()->with('lines')->findOrFail($cartId);
    $product->inventory->refresh();

    expect($cart->lines)->toHaveCount(0)
        ->and((float) $cart->total_final)->toBe(0.0)
        ->and($product->inventory->stock_reserved)->toBe(0);
});

it('adds a discount code to an existing cart through api and recalculates totals', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass);
    $user = createUserForCart('user-discount@example.test');

    $discountModel = config('venditio.models.discount');
    $discountModel::query()->create([
        'discountable_type' => null,
        'discountable_id' => null,
        'type' => DiscountType::Percentage,
        'value' => 10,
        'code' => 'CHECKOUT10',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'apply_to_cart_total' => true,
    ]);

    $prefix = config('venditio.routes.api.v1.prefix');

    $cartId = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 2],
        ],
    ])->assertCreated()->json('id');

    postJson($prefix . '/carts/' . $cartId . '/add_discount', [
        'discount_code' => 'CHECKOUT10',
    ])->assertOk()
        ->assertJsonPath('discount_code', 'CHECKOUT10')
        ->assertJsonPath('discount_amount', 24.4)
        ->assertJsonPath('total_final', 219.6);
});

it('returns 422 when adding a cart total discount code not eligible for cart total discounts', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass);
    $user = createUserForCart('user-invalid-add-discount@example.test');
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

    postJson($prefix . '/carts/' . $cartId . '/add_discount', [
        'discount_code' => 'TEST10',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.discount_code.0', 'The discount code [TEST10] is invalid or not eligible for cart total discounts.');
});

it('returns 422 when creating a cart with a non eligible cart total discount code', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass);
    $user = createUserForCart('user-invalid-cart-create-discount@example.test');
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

    postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'discount_code' => 'TEST10',
        'lines' => [
            ['product_id' => $product->getKey(), 'qty' => 1],
        ],
    ])->assertUnprocessable()
        ->assertJsonPath('errors.discount_code.0', 'The discount code [TEST10] is invalid or not eligible for cart total discounts.');
});

it('returns 422 when updating a cart with a non eligible cart total discount code', function () {
    $taxClass = TaxClass::factory()->create();
    setupCartTaxEnvironment($taxClass);
    $product = createCartProduct($taxClass);
    $user = createUserForCart('user-invalid-cart-update-discount@example.test');
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

    patchJson($prefix . '/carts/' . $cartId, [
        'discount_code' => 'TEST10',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.discount_code.0', 'The discount code [TEST10] is invalid or not eligible for cart total discounts.');
});
