<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\{FreeGiftMode, FreeGiftProductMatchMode, FreeGiftSelectionMode, ProductStatus};
use PictaStudio\Venditio\Models\{Country, CountryTaxClass, Currency, FreeGift, Product, TaxClass, User};

use function Pest\Laravel\{assertDatabaseHas, patchJson, postJson};

uses(RefreshDatabase::class);

function setupFreeGiftCartTaxEnvironment(TaxClass $taxClass): void
{
    $currencyId = Currency::query()->firstOrCreate(
        ['code' => 'EUR'],
        ['name' => 'EUR', 'exchange_rate' => 1, 'is_enabled' => true, 'is_default' => true]
    )->getKey();

    $country = Country::query()->firstOrCreate(
        ['iso_2' => 'IT'],
        [
            'name' => 'Italy',
            'iso_3' => 'ITA',
            'phone_code' => '+39',
            'currency_id' => $currencyId,
            'flag_emoji' => 'it',
            'capital' => 'Rome',
            'native' => 'Italia',
        ]
    );

    CountryTaxClass::query()->updateOrCreate([
        'country_id' => $country->getKey(),
        'tax_class_id' => $taxClass->getKey(),
    ], [
        'rate' => 22,
    ]);
}

function createFreeGiftCartProduct(
    TaxClass $taxClass,
    string $name,
    float $price = 100,
    float $weight = 1,
): Product {
    $product = Product::factory()->create([
        'name' => $name,
        'tax_class_id' => $taxClass->getKey(),
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
        'weight' => $weight,
        'length' => 10,
        'width' => 10,
        'height' => 10,
    ]);

    $product->inventory()->updateOrCreate([], [
        'stock' => 100,
        'stock_reserved' => 0,
        'stock_available' => 100,
        'stock_min' => 0,
        'price' => $price,
        'price_includes_tax' => false,
        'purchase_price' => round($price / 2, 2),
    ]);

    return $product->refresh();
}

function createFreeGiftCartUser(string $email): User
{
    return User::query()->create([
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $email,
        'phone' => '123456789',
    ]);
}

function createCartFreeGiftCampaign(
    array $attributes,
    array $qualifyingProductIds = [],
    array $giftProductIds = [],
    array $qualifyingUserIds = [],
): FreeGift {
    $freeGift = FreeGift::factory()->create(array_merge([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
        'active' => true,
    ], $attributes));

    $freeGift->qualifyingUsers()->sync($qualifyingUserIds);
    $freeGift->qualifyingProducts()->sync($qualifyingProductIds);
    $freeGift->giftProducts()->sync($giftProductIds);

    return $freeGift->refresh();
}

function cartResponseLine(array $responseLines, int $productId): ?array
{
    $line = collect($responseLines)->firstWhere('product_id', $productId);

    return is_array($line) ? $line : null;
}

it('automatically adds eligible free gifts, reserves stock, contributes to weight, and propagates to orders', function () {
    $taxClass = TaxClass::factory()->create();
    setupFreeGiftCartTaxEnvironment($taxClass);
    $purchasedProduct = createFreeGiftCartProduct($taxClass, 'Main Product', 100, 1.5);
    $giftProduct = createFreeGiftCartProduct($taxClass, 'Gift Product', 50, 2.5);
    $user = createFreeGiftCartUser('automatic-gift@example.test');
    $freeGift = createCartFreeGiftCampaign([
        'name' => 'Automatic Gift',
        'mode' => FreeGiftMode::Automatic,
        'selection_mode' => FreeGiftSelectionMode::Multiple,
        'allow_decline' => false,
        'product_match_mode' => FreeGiftProductMatchMode::Any,
    ], [$purchasedProduct->getKey()], [$giftProduct->getKey()]);
    $prefix = config('venditio.routes.api.v1.prefix');

    $response = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'user_first_name' => $user->first_name,
        'user_last_name' => $user->last_name,
        'user_email' => $user->email,
        'lines' => [
            ['product_id' => $purchasedProduct->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()
        ->assertJsonPath('total_final', 122)
        ->assertJsonPath('free_gifts.0.id', $freeGift->getKey());

    $giftLine = cartResponseLine($response->json('lines'), $giftProduct->getKey());

    expect($giftLine)->not->toBeNull()
        ->and((bool) $giftLine['is_free_gift'])->toBeTrue()
        ->and((int) $giftLine['free_gift_id'])->toBe($freeGift->getKey())
        ->and((float) $giftLine['unit_price'])->toBe(0.0)
        ->and((float) $giftLine['total_final_price'])->toBe(0.0)
        ->and((float) $response->json('specific_weight'))->toBe(4.0);

    $purchasedProduct->inventory->refresh();
    $giftProduct->inventory->refresh();

    expect($purchasedProduct->inventory->stock_reserved)->toBe(1)
        ->and($giftProduct->inventory->stock_reserved)->toBe(1);

    $orderResponse = postJson($prefix . '/orders', [
        'cart_id' => $response->json('id'),
    ])->assertOk();

    $orderGiftLine = cartResponseLine($orderResponse->json('lines'), $giftProduct->getKey());

    expect($orderGiftLine)->not->toBeNull()
        ->and((bool) $orderGiftLine['is_free_gift'])->toBeTrue()
        ->and((int) $orderGiftLine['free_gift_id'])->toBe($freeGift->getKey())
        ->and((float) $orderGiftLine['total_final_price'])->toBe(0.0);

    assertDatabaseHas('order_lines', [
        'order_id' => $orderResponse->json('id'),
        'product_id' => $giftProduct->getKey(),
        'is_free_gift' => true,
        'free_gift_id' => $freeGift->getKey(),
    ]);
});

it('lets users decline and later re-accept automatic free gifts when the campaign allows it', function () {
    $taxClass = TaxClass::factory()->create();
    setupFreeGiftCartTaxEnvironment($taxClass);
    $purchasedProduct = createFreeGiftCartProduct($taxClass, 'Purchased Product');
    $giftProduct = createFreeGiftCartProduct($taxClass, 'Declinable Gift', 40);
    $user = createFreeGiftCartUser('decline-gift@example.test');
    $freeGift = createCartFreeGiftCampaign([
        'name' => 'Declinable Automatic Gift',
        'mode' => FreeGiftMode::Automatic,
        'selection_mode' => FreeGiftSelectionMode::Multiple,
        'allow_decline' => true,
        'product_match_mode' => FreeGiftProductMatchMode::Any,
    ], [$purchasedProduct->getKey()], [$giftProduct->getKey()]);
    $prefix = config('venditio.routes.api.v1.prefix');

    $cartResponse = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'lines' => [
            ['product_id' => $purchasedProduct->getKey(), 'qty' => 1],
        ],
    ])->assertCreated();

    patchJson($prefix . '/carts/' . $cartResponse->json('id') . '/free_gifts', [
        'free_gifts' => [
            [
                'free_gift_id' => $freeGift->getKey(),
                'declined_product_ids' => [$giftProduct->getKey()],
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('free_gifts.0.declined_product_ids.0', $giftProduct->getKey());

    $declinedLines = collect(
        patchJson($prefix . '/carts/' . $cartResponse->json('id') . '/free_gifts', [
            'free_gifts' => [
                [
                    'free_gift_id' => $freeGift->getKey(),
                    'declined_product_ids' => [$giftProduct->getKey()],
                ],
            ],
        ])->assertOk()->json('lines')
    );

    expect($declinedLines->firstWhere('product_id', $giftProduct->getKey()))->toBeNull();

    $giftProduct->inventory->refresh();
    expect($giftProduct->inventory->stock_reserved)->toBe(0);

    patchJson($prefix . '/carts/' . $cartResponse->json('id') . '/free_gifts', [
        'free_gifts' => [
            [
                'free_gift_id' => $freeGift->getKey(),
                'selected_product_ids' => [$giftProduct->getKey()],
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('free_gifts.0.selected_product_ids.0', $giftProduct->getKey())
        ->assertJsonPath('free_gifts.0.in_cart_product_ids.0', $giftProduct->getKey());
});

it('requires manual single-choice campaigns to select at most one gift and adds the chosen gift only after selection', function () {
    $taxClass = TaxClass::factory()->create();
    setupFreeGiftCartTaxEnvironment($taxClass);
    $purchasedProduct = createFreeGiftCartProduct($taxClass, 'Manual Product');
    $giftProductA = createFreeGiftCartProduct($taxClass, 'Manual Gift A', 20);
    $giftProductB = createFreeGiftCartProduct($taxClass, 'Manual Gift B', 30);
    $freeGift = createCartFreeGiftCampaign([
        'name' => 'Manual Single Gift',
        'mode' => FreeGiftMode::Manual,
        'selection_mode' => FreeGiftSelectionMode::Single,
        'allow_decline' => true,
        'product_match_mode' => FreeGiftProductMatchMode::Any,
    ], [$purchasedProduct->getKey()], [$giftProductA->getKey(), $giftProductB->getKey()]);
    $prefix = config('venditio.routes.api.v1.prefix');

    $cartResponse = postJson($prefix . '/carts', [
        'lines' => [
            ['product_id' => $purchasedProduct->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()
        ->assertJsonPath('free_gifts.0.id', $freeGift->getKey())
        ->assertJsonCount(1, 'lines');

    patchJson($prefix . '/carts/' . $cartResponse->json('id') . '/free_gifts', [
        'free_gifts' => [
            [
                'free_gift_id' => $freeGift->getKey(),
                'selected_product_ids' => [$giftProductA->getKey(), $giftProductB->getKey()],
            ],
        ],
    ])->assertStatus(422)
        ->assertJsonPath('message', 'Single-choice free gift campaigns accept at most one selected product.');

    patchJson($prefix . '/carts/' . $cartResponse->json('id') . '/free_gifts', [
        'free_gifts' => [
            [
                'free_gift_id' => $freeGift->getKey(),
                'selected_product_ids' => [$giftProductB->getKey()],
            ],
        ],
    ])->assertOk()
        ->assertJsonCount(2, 'lines')
        ->assertJsonPath('free_gifts.0.selected_product_ids.0', $giftProductB->getKey())
        ->assertJsonPath('free_gifts.0.in_cart_product_ids.0', $giftProductB->getKey())
        ->assertJsonPath('total_final', 122);
});

it('adds multiple selected gifts for manual multiple-choice campaigns', function () {
    $taxClass = TaxClass::factory()->create();
    setupFreeGiftCartTaxEnvironment($taxClass);
    $purchasedProduct = createFreeGiftCartProduct($taxClass, 'Multiple Product');
    $giftProductA = createFreeGiftCartProduct($taxClass, 'Multiple Gift A', 25);
    $giftProductB = createFreeGiftCartProduct($taxClass, 'Multiple Gift B', 35);
    $freeGift = createCartFreeGiftCampaign([
        'name' => 'Manual Multiple Gift',
        'mode' => FreeGiftMode::Manual,
        'selection_mode' => FreeGiftSelectionMode::Multiple,
        'allow_decline' => true,
        'product_match_mode' => FreeGiftProductMatchMode::Any,
    ], [$purchasedProduct->getKey()], [$giftProductA->getKey(), $giftProductB->getKey()]);
    $prefix = config('venditio.routes.api.v1.prefix');

    $cartResponse = postJson($prefix . '/carts', [
        'lines' => [
            ['product_id' => $purchasedProduct->getKey(), 'qty' => 1],
        ],
    ])->assertCreated();

    $response = patchJson($prefix . '/carts/' . $cartResponse->json('id') . '/free_gifts', [
        'free_gifts' => [
            [
                'free_gift_id' => $freeGift->getKey(),
                'selected_product_ids' => [$giftProductA->getKey(), $giftProductB->getKey()],
            ],
        ],
    ])->assertOk()
        ->assertJsonCount(3, 'lines');

    $selectedIds = collect($response->json('free_gifts.0.selected_product_ids'))
        ->map(fn (mixed $id): int => (int) $id)
        ->sort()
        ->values()
        ->all();
    $inCartIds = collect($response->json('free_gifts.0.in_cart_product_ids'))
        ->map(fn (mixed $id): int => (int) $id)
        ->sort()
        ->values()
        ->all();

    expect($selectedIds)->toBe([$giftProductA->getKey(), $giftProductB->getKey()])
        ->and($inCartIds)->toBe([$giftProductA->getKey(), $giftProductB->getKey()]);
});

it('supports stacked free gift campaigns and all-product matching for specific users', function () {
    $taxClass = TaxClass::factory()->create();
    setupFreeGiftCartTaxEnvironment($taxClass);
    $productA = createFreeGiftCartProduct($taxClass, 'Product A');
    $productB = createFreeGiftCartProduct($taxClass, 'Product B');
    $giftProductA = createFreeGiftCartProduct($taxClass, 'Stacked Gift A', 15);
    $giftProductB = createFreeGiftCartProduct($taxClass, 'Stacked Gift B', 18);
    $user = createFreeGiftCartUser('stacking-gifts@example.test');
    createCartFreeGiftCampaign([
        'name' => 'Any Product Gift',
        'mode' => FreeGiftMode::Automatic,
        'selection_mode' => FreeGiftSelectionMode::Multiple,
        'product_match_mode' => FreeGiftProductMatchMode::Any,
    ], [$productA->getKey()], [$giftProductA->getKey()]);
    $allProductsCampaign = createCartFreeGiftCampaign([
        'name' => 'All Products Gift',
        'mode' => FreeGiftMode::Automatic,
        'selection_mode' => FreeGiftSelectionMode::Multiple,
        'product_match_mode' => FreeGiftProductMatchMode::All,
    ], [$productA->getKey(), $productB->getKey()], [$giftProductB->getKey()], [$user->getKey()]);
    $prefix = config('venditio.routes.api.v1.prefix');

    $cartResponse = postJson($prefix . '/carts', [
        'user_id' => $user->getKey(),
        'lines' => [
            ['product_id' => $productA->getKey(), 'qty' => 1],
        ],
    ])->assertCreated()
        ->assertJsonCount(2, 'lines')
        ->assertJsonCount(1, 'free_gifts');

    $updatedResponse = postJson($prefix . '/carts/' . $cartResponse->json('id') . '/add_lines', [
        'lines' => [
            ['product_id' => $productB->getKey(), 'qty' => 1],
        ],
    ])->assertOk()
        ->assertJsonCount(4, 'lines')
        ->assertJsonCount(2, 'free_gifts')
        ->assertJsonPath('free_gifts.1.id', $allProductsCampaign->getKey());

    $lineProductIds = collect($updatedResponse->json('lines'))
        ->pluck('product_id')
        ->map(fn (mixed $id): int => (int) $id)
        ->sort()
        ->values()
        ->all();

    expect($lineProductIds)->toBe(collect([
        $giftProductA->getKey(),
        $giftProductB->getKey(),
        $productA->getKey(),
        $productB->getKey(),
    ])->sort()->values()->all());
});
