<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PictaStudio\Venditio\Dto\{CartDto, OrderDto};
use PictaStudio\Venditio\Enums\{DiscountType, ProductStatus};
use PictaStudio\Venditio\Models\{Country, CountryTaxClass, Currency, DiscountApplication, Product, ProductCategory, ProductCollection, TaxClass, User};
use PictaStudio\Venditio\Pipelines\Cart\{CartCreationPipeline, CartUpdatePipeline};
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

function setupTaxEnvironment(TaxClass $taxClass): void
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

function createProduct(float $price, TaxClass $taxClass, bool $priceIncludesTax = false): Product
{
    /** @var Product $product */
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
        'price_includes_tax' => $priceIncludesTax,
        'purchase_price' => null,
    ]);

    return $product->refresh();
}

function taxAddresses(int $billingCountryId, ?int $shippingCountryId = null): array
{
    return [
        'billing' => [
            'country_id' => $billingCountryId,
        ],
        'shipping' => [
            'country_id' => $shippingCountryId ?? $billingCountryId,
        ],
    ];
}

function createCartForUser(User $user, int $productId, int $qty = 1)
{
    return CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'user_id' => $user->getKey(),
            'user_first_name' => $user->first_name,
            'user_last_name' => $user->last_name,
            'user_email' => $user->email,
            'lines' => [
                [
                    'product_id' => $productId,
                    'qty' => $qty,
                ],
            ],
        ])
    );
}

it('applies a category discount through polymorphic relations during cart calculation', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $product = createProduct(100, $taxClass);
    $category = ProductCategory::factory()->create([
        'active' => true,
        'sort_order' => 1,
    ]);
    $product->categories()->attach($category->getKey());

    $category->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'code' => 'CAT10',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'lines' => [
                [
                    'product_id' => $product->getKey(),
                    'qty' => 2,
                ],
            ],
        ])
    )->load('lines');

    $line = $cart->lines->first();

    expect((float) $line->unit_discount)->toBe(10.0)
        ->and((float) $line->discount_amount)->toBe(20.0)
        ->and($line->discount_code)->toBe('CAT10')
        ->and((float) $cart->discount_amount)->toBe(0.0);
});

it('applies a collection discount through polymorphic relations during cart calculation', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $product = createProduct(100, $taxClass);
    $collection = ProductCollection::factory()->create([
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
    ]);
    $product->collections()->attach($collection->getKey());

    $collection->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 15,
        'code' => 'COL15',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'lines' => [
                [
                    'product_id' => $product->getKey(),
                    'qty' => 2,
                ],
            ],
        ])
    )->load('lines');

    $line = $cart->lines->first();

    expect((float) $line->unit_discount)->toBe(15.0)
        ->and((float) $line->discount_amount)->toBe(30.0)
        ->and($line->discount_code)->toBe('COL15');
});

it('propagates multiple applicable discounts on the same cart line', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $product = createProduct(100, $taxClass);
    $category = ProductCategory::factory()->create([
        'active' => true,
    ]);
    $product->categories()->attach($category->getKey());

    $product->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'code' => 'PRD10',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'stop_after_propagation' => false,
    ]);
    $category->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 50,
        'code' => 'CAT50',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'stop_after_propagation' => false,
    ]);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'lines' => [
                [
                    'product_id' => $product->getKey(),
                    'qty' => 1,
                ],
            ],
        ])
    )->load('lines');

    $line = $cart->lines->first();

    expect((float) $line->unit_discount)->toBe(55.0)
        ->and((float) $line->unit_final_price)->toBe(45.0);
});

it('stops propagation on cart line discounts when stop_after_propagation is true', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $product = createProduct(100, $taxClass);
    $category = ProductCategory::factory()->create([
        'active' => true,
    ]);
    $product->categories()->attach($category->getKey());

    $category->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 50,
        'code' => 'CAT50STOP',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'priority' => 10,
        'stop_after_propagation' => true,
    ]);
    $product->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'code' => 'PRD10STOP',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'priority' => 0,
        'stop_after_propagation' => false,
    ]);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'lines' => [
                [
                    'product_id' => $product->getKey(),
                    'qty' => 1,
                ],
            ],
        ])
    )->load('lines');

    $line = $cart->lines->first();

    expect((float) $line->unit_discount)->toBe(50.0)
        ->and((float) $line->unit_final_price)->toBe(50.0);
});

it('applies discounts only once per cart when the rule is enabled', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $product = createProduct(50, $taxClass);
    $product->discounts()->create([
        'type' => DiscountType::Fixed,
        'value' => 15,
        'code' => 'ONCE15',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'apply_once_per_cart' => true,
    ]);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'lines' => [
                ['product_id' => $product->getKey(), 'qty' => 1],
                ['product_id' => $product->getKey(), 'qty' => 1],
            ],
        ])
    )->load('lines');

    $discountedLines = $cart->lines->filter(fn ($line) => $line->discount_code === 'ONCE15');
    $notDiscountedLines = $cart->lines->filter(fn ($line) => blank($line->discount_code));

    expect($discountedLines)->toHaveCount(1)
        ->and($notDiscountedLines)->toHaveCount(1)
        ->and((float) $discountedLines->first()->unit_discount)->toBe(15.0)
        ->and((float) $notDiscountedLines->first()->unit_discount)->toBe(0.0);
});

it('enforces per-user usage limits after order registration', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $user = User::query()->create([
        'first_name' => 'Mario',
        'last_name' => 'Rossi',
        'email' => 'mario@example.test',
        'phone' => '123456789',
    ]);
    $product = createProduct(80, $taxClass);

    $product->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 20,
        'code' => 'USER20',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'one_per_user' => true,
    ]);

    $firstCart = createCartForUser($user, $product->getKey())->load('lines');
    expect($firstCart->lines->first()->discount_code)->toBe('USER20');

    OrderCreationPipeline::make()->run(OrderDto::fromCart($firstCart));

    expect(DiscountApplication::query()
        ->where('user_id', $user->getKey())
        ->count())->toBe(1);

    $secondCart = createCartForUser($user, $product->getKey())->load('lines');
    $secondLine = $secondCart->lines->first();

    expect(blank($secondLine->discount_code))->toBeTrue()
        ->and((float) $secondLine->unit_discount)->toBe(0.0);
});

it('increments uses for all propagated line discounts when converting cart to order', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $user = User::query()->create([
        'first_name' => 'Uses',
        'last_name' => 'Propagation',
        'email' => 'uses-propagation@example.test',
        'phone' => '123456789',
    ]);

    $product = createProduct(100, $taxClass);
    $category = ProductCategory::factory()->create([
        'active' => true,
    ]);
    $product->categories()->attach($category->getKey());

    $productDiscount = $product->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 10,
        'code' => 'USES-PRD10',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'stop_after_propagation' => false,
    ]);
    $categoryDiscount = $category->discounts()->create([
        'type' => DiscountType::Percentage,
        'value' => 50,
        'code' => 'USES-CAT50',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'stop_after_propagation' => false,
    ]);

    $cart = createCartForUser($user, $product->getKey())->load('lines');
    $order = OrderCreationPipeline::make()->run(OrderDto::fromCart($cart))->load('lines');
    $orderLine = $order->lines->first();

    expect((int) $productDiscount->refresh()->uses)->toBe(1)
        ->and((int) $categoryDiscount->refresh()->uses)->toBe(1)
        ->and(
            DiscountApplication::query()
                ->where('order_line_id', $orderLine->getKey())
                ->count()
        )->toBe(2);
});

it('applies cart total discount code at checkout', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $product = createProduct(100, $taxClass);

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

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'discount_code' => 'CHECKOUT10',
            'lines' => [
                [
                    'product_id' => $product->getKey(),
                    'qty' => 2,
                ],
            ],
        ])
    )->load('lines');

    expect($cart->discount_code)->toBe('CHECKOUT10')
        ->and((float) $cart->sub_total)->toBe(244.0)
        ->and((float) $cart->discount_amount)->toBe(24.4)
        ->and((float) $cart->total_final)->toBe(219.6);
});

it('applies discounts only to the configured user', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $allowedUser = User::query()->create([
        'first_name' => 'Allowed',
        'last_name' => 'User',
        'email' => 'allowed-user@example.test',
        'phone' => '123456789',
    ]);
    $blockedUser = User::query()->create([
        'first_name' => 'Blocked',
        'last_name' => 'User',
        'email' => 'blocked-user@example.test',
        'phone' => '123456789',
    ]);
    $product = createProduct(80, $taxClass);

    $discountModel = config('venditio.models.discount');
    $discountModel::query()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'code' => 'USR10',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'discountable_type' => 'user',
        'discountable_id' => $allowedUser->getKey(),
    ]);

    $allowedCart = createCartForUser($allowedUser, $product->getKey())->load('lines');
    $blockedCart = createCartForUser($blockedUser, $product->getKey())->load('lines');

    expect($allowedCart->lines->first()->discount_code)->toBe('USR10')
        ->and(blank($blockedCart->lines->first()->discount_code))->toBeTrue();
});

it('enforces minimum order total to apply a discount', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $product = createProduct(100, $taxClass);
    $product->discounts()->create([
        'type' => DiscountType::Fixed,
        'value' => 15,
        'code' => 'MIN200',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'minimum_order_total' => 200,
    ]);

    $singleQtyCart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'lines' => [
                ['product_id' => $product->getKey(), 'qty' => 1],
            ],
        ])
    )->load('lines');

    $doubleQtyCart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'lines' => [
                ['product_id' => $product->getKey(), 'qty' => 2],
            ],
        ])
    )->load('lines');

    expect(blank($singleQtyCart->lines->first()->discount_code))->toBeTrue()
        ->and($doubleQtyCart->lines->first()->discount_code)->toBe('MIN200');
});

it('applies free shipping when cart total discount enables it', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $user = User::query()->create([
        'first_name' => 'Shipping',
        'last_name' => 'Free',
        'email' => 'free-shipping@example.test',
        'phone' => '123456789',
    ]);
    $product = createProduct(100, $taxClass);

    $discountModel = config('venditio.models.discount');
    $discountModel::query()->create([
        'discountable_type' => null,
        'discountable_id' => null,
        'type' => DiscountType::Fixed,
        'value' => 0,
        'code' => 'SHIPFREE',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'apply_to_cart_total' => true,
        'free_shipping' => true,
    ]);

    $cart = createCartForUser($user, $product->getKey());
    $cart->fill(['shipping_fee' => 20])->save();

    $updatedCart = CartUpdatePipeline::make()->run(
        CartDto::fromArray([
            'cart' => $cart->refresh(),
            'discount_code' => 'SHIPFREE',
        ])
    );

    expect($updatedCart->discount_code)->toBe('SHIPFREE')
        ->and((float) $updatedCart->shipping_fee)->toBe(0.0)
        ->and((float) $updatedCart->discount_amount)->toBe(0.0);
});

it('removes tax from VAT-inclusive inventory price when calculating cart line totals', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $product = createProduct(122, $taxClass, true);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'lines' => [
                [
                    'product_id' => $product->getKey(),
                    'qty' => 1,
                ],
            ],
        ])
    )->load('lines');

    $line = $cart->lines->first();

    expect((float) $line->unit_final_price)->toBe(122.0)
        ->and((float) $line->unit_final_price_taxable)->toBe(100.0)
        ->and((float) $line->unit_final_price_tax)->toBe(22.0)
        ->and((float) $line->total_final_price)->toBe(122.0);
});

it('recalculates tax correctly for VAT-inclusive prices after discounts', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $product = createProduct(122, $taxClass, true);
    $product->discounts()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'code' => 'GROSS10',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'lines' => [
                [
                    'product_id' => $product->getKey(),
                    'qty' => 1,
                ],
            ],
        ])
    )->load('lines');

    $line = $cart->lines->first();

    expect((float) $line->unit_final_price)->toBe(112.0)
        ->and((float) data_get($line->product_data, 'price_calculated.price'))->toBe(122.0)
        ->and((float) data_get($line->product_data, 'price_calculated.price_final'))->toBe(112.0)
        ->and((float) $line->unit_final_price_taxable)->toBe(91.8)
        ->and((float) $line->unit_final_price_tax)->toBe(20.2)
        ->and((float) $line->total_final_price)->toBe(112.0);
});

it('preserves VAT-inclusive totals when converting cart to order', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $user = User::query()->create([
        'first_name' => 'Order',
        'last_name' => 'Vat',
        'email' => 'order-vat@example.test',
        'phone' => '123456789',
    ]);

    $product = createProduct(122, $taxClass, true);
    $product->discounts()->create([
        'type' => DiscountType::Fixed,
        'value' => 10,
        'code' => 'ORDERLINE10',
        'active' => true,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
    ]);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'user_id' => $user->getKey(),
            'user_first_name' => $user->first_name,
            'user_last_name' => $user->last_name,
            'user_email' => $user->email,
            'lines' => [
                [
                    'product_id' => $product->getKey(),
                    'qty' => 1,
                ],
            ],
        ])
    )->load('lines');

    $order = OrderCreationPipeline::make()->run(OrderDto::fromCart($cart))->load('lines');
    $orderLine = $order->lines->first();

    expect((float) $cart->total_final)->toBe(112.0)
        ->and((float) data_get($cart->lines->first()->product_data, 'price_calculated.price'))->toBe(122.0)
        ->and((float) data_get($cart->lines->first()->product_data, 'price_calculated.price_final'))->toBe(112.0)
        ->and((float) $order->total_final)->toBe(112.0)
        ->and((float) $orderLine->unit_final_price)->toBe(112.0)
        ->and((float) data_get($orderLine->product_data, 'price_calculated.price'))->toBe(122.0)
        ->and((float) data_get($orderLine->product_data, 'price_calculated.price_final'))->toBe(112.0)
        ->and((float) $orderLine->unit_final_price_taxable)->toBe(91.8)
        ->and((float) $orderLine->unit_final_price_tax)->toBe(20.2)
        ->and((float) $orderLine->total_final_price)->toBe(112.0);
});

it('uses the billing address country tax rate when converting cart to order', function () {
    $taxClass = TaxClass::factory()->create();
    setupTaxEnvironment($taxClass);

    $currencyId = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'EUR', 'exchange_rate' => 1, 'is_enabled' => true, 'is_default' => false]
    )->getKey();

    $germany = Country::query()->create([
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
        'country_id' => $germany->getKey(),
        'tax_class_id' => $taxClass->getKey(),
        'rate' => 10,
    ]);

    $italyId = Country::query()->where('iso_2', 'IT')->value('id');
    $product = createProduct(100, $taxClass);

    $cart = CartCreationPipeline::make()->run(
        CartDto::fromArray([
            'addresses' => taxAddresses($italyId, $italyId),
            'lines' => [
                [
                    'product_id' => $product->getKey(),
                    'qty' => 1,
                ],
            ],
        ])
    )->load('lines');

    expect((float) $cart->lines->first()->tax_rate)->toBe(22.0);

    $cart->update([
        'addresses' => taxAddresses($germany->getKey(), $italyId),
    ]);

    $order = OrderCreationPipeline::make()->run(OrderDto::fromCart($cart->fresh('lines')))->load('lines');
    $orderLine = $order->lines->first();

    expect((float) $orderLine->tax_rate)->toBe(10.0)
        ->and((float) $orderLine->unit_final_price_taxable)->toBe(100.0)
        ->and((float) $orderLine->unit_final_price_tax)->toBe(10.0)
        ->and((float) $orderLine->total_final_price)->toBe(110.0)
        ->and((float) $order->total_final)->toBe(110.0);
});
