<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PictaStudio\Venditio\Enums\{FreeGiftMode, FreeGiftProductMatchMode, FreeGiftSelectionMode, ProductStatus};
use PictaStudio\Venditio\Models\{FreeGift, Product, TaxClass, User};

use function Pest\Laravel\{assertDatabaseHas, assertDatabaseMissing, deleteJson, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

function createAdminFreeGiftUser(string $email): User
{
    return User::query()->create([
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => $email,
        'phone' => '123456789',
    ]);
}

function createAdminFreeGiftProduct(string $name): Product
{
    $product = Product::factory()->create([
        'name' => $name,
        'status' => ProductStatus::Published,
        'active' => true,
        'visible_from' => now()->subDay(),
        'visible_until' => now()->addDay(),
        'tax_class_id' => TaxClass::factory(),
    ]);

    $product->inventory()->updateOrCreate([], [
        'stock' => 100,
        'stock_reserved' => 0,
        'stock_available' => 100,
        'stock_min' => 0,
        'price' => 20,
        'price_includes_tax' => false,
        'purchase_price' => 10,
    ]);

    return $product->refresh();
}

it('creates and shows free gift campaigns with related ids and resources', function () {
    $qualifyingUser = createAdminFreeGiftUser('free-gift-admin-user@example.test');
    $qualifyingProduct = createAdminFreeGiftProduct('Qualifying Product');
    $giftProductA = createAdminFreeGiftProduct('Gift Product A');
    $giftProductB = createAdminFreeGiftProduct('Gift Product B');
    $prefix = config('venditio.routes.api.v1.prefix');

    $response = postJson($prefix . '/free_gifts', [
        'name' => 'Spring Gifts',
        'mode' => FreeGiftMode::Manual->value,
        'selection_mode' => FreeGiftSelectionMode::Multiple->value,
        'allow_decline' => true,
        'active' => true,
        'starts_at' => now()->subHour()->toDateTimeString(),
        'ends_at' => now()->addDay()->toDateTimeString(),
        'minimum_cart_subtotal' => 50,
        'maximum_cart_subtotal' => 250,
        'minimum_cart_quantity' => 1,
        'maximum_cart_quantity' => 5,
        'product_match_mode' => FreeGiftProductMatchMode::Any->value,
        'qualifying_user_ids' => [$qualifyingUser->getKey()],
        'qualifying_product_ids' => [$qualifyingProduct->getKey()],
        'gift_product_ids' => [$giftProductA->getKey(), $giftProductB->getKey()],
    ])->assertCreated()
        ->assertJsonPath('name', 'Spring Gifts')
        ->assertJsonPath('mode', FreeGiftMode::Manual->value)
        ->assertJsonPath('selection_mode', FreeGiftSelectionMode::Multiple->value)
        ->assertJsonPath('product_match_mode', FreeGiftProductMatchMode::Any->value);

    $freeGiftId = (int) $response->json('id');
    $giftProductIds = collect($response->json('gift_product_ids'))
        ->map(fn (mixed $id): int => (int) $id)
        ->sort()
        ->values()
        ->all();

    expect($giftProductIds)->toBe([
        $giftProductA->getKey(),
        $giftProductB->getKey(),
    ]);

    assertDatabaseHas('free_gifts', [
        'id' => $freeGiftId,
        'name' => 'Spring Gifts',
        'mode' => FreeGiftMode::Manual->value,
        'selection_mode' => FreeGiftSelectionMode::Multiple->value,
    ]);
    assertDatabaseHas('free_gift_user', [
        'free_gift_id' => $freeGiftId,
        'user_id' => $qualifyingUser->getKey(),
    ]);
    assertDatabaseHas('free_gift_qualifying_product', [
        'free_gift_id' => $freeGiftId,
        'product_id' => $qualifyingProduct->getKey(),
    ]);
    assertDatabaseHas('free_gift_product', [
        'free_gift_id' => $freeGiftId,
        'product_id' => $giftProductA->getKey(),
    ]);
    assertDatabaseHas('free_gift_product', [
        'free_gift_id' => $freeGiftId,
        'product_id' => $giftProductB->getKey(),
    ]);

    getJson($prefix . '/free_gifts/' . $freeGiftId)
        ->assertOk()
        ->assertJsonPath('id', $freeGiftId)
        ->assertJsonPath('qualifying_users.0.id', $qualifyingUser->getKey())
        ->assertJsonPath('qualifying_products.0.id', $qualifyingProduct->getKey());
});

it('creates inactive free gift campaigns with campaign defaults', function () {
    $prefix = config('venditio.routes.api.v1.prefix');

    $response = postJson($prefix . '/free_gifts', [
        'name' => 'Draft Gift',
        'mode' => FreeGiftMode::Manual->value,
    ])->assertCreated()
        ->assertJsonPath('name', 'Draft Gift')
        ->assertJsonPath('selection_mode', FreeGiftSelectionMode::Single->value)
        ->assertJsonPath('product_match_mode', FreeGiftProductMatchMode::Any->value)
        ->assertJsonPath('active', false)
        ->assertJsonPath('minimum_cart_subtotal', null)
        ->assertJsonPath('maximum_cart_subtotal', null)
        ->assertJsonPath('minimum_cart_quantity', null)
        ->assertJsonPath('maximum_cart_quantity', null)
        ->assertJsonPath('gift_product_ids', []);

    assertDatabaseHas('free_gifts', [
        'id' => $response->json('id'),
        'name' => 'Draft Gift',
        'selection_mode' => FreeGiftSelectionMode::Single->value,
        'product_match_mode' => FreeGiftProductMatchMode::Any->value,
        'active' => false,
        'minimum_cart_subtotal' => null,
        'maximum_cart_subtotal' => null,
        'minimum_cart_quantity' => null,
        'maximum_cart_quantity' => null,
    ]);
});

it('updates free gift campaigns by syncing related ids and supports deletion', function () {
    $oldUser = createAdminFreeGiftUser('old-free-gift-user@example.test');
    $newUser = createAdminFreeGiftUser('new-free-gift-user@example.test');
    $oldQualifyingProduct = createAdminFreeGiftProduct('Old Qualifier');
    $newQualifyingProduct = createAdminFreeGiftProduct('New Qualifier');
    $oldGiftProduct = createAdminFreeGiftProduct('Old Gift');
    $newGiftProduct = createAdminFreeGiftProduct('New Gift');
    $freeGift = FreeGift::factory()->create([
        'mode' => FreeGiftMode::Automatic,
        'selection_mode' => FreeGiftSelectionMode::Multiple,
    ]);
    $freeGift->qualifyingUsers()->sync([$oldUser->getKey()]);
    $freeGift->qualifyingProducts()->sync([$oldQualifyingProduct->getKey()]);
    $freeGift->giftProducts()->sync([$oldGiftProduct->getKey()]);
    $prefix = config('venditio.routes.api.v1.prefix');

    patchJson($prefix . '/free_gifts/' . $freeGift->getKey(), [
        'name' => 'Updated Campaign',
        'selection_mode' => FreeGiftSelectionMode::Single->value,
        'allow_decline' => true,
        'product_match_mode' => FreeGiftProductMatchMode::All->value,
        'qualifying_user_ids' => [$newUser->getKey()],
        'qualifying_product_ids' => [$newQualifyingProduct->getKey()],
        'gift_product_ids' => [$newGiftProduct->getKey()],
    ])->assertOk()
        ->assertJsonPath('name', 'Updated Campaign')
        ->assertJsonPath('selection_mode', FreeGiftSelectionMode::Single->value)
        ->assertJsonPath('allow_decline', true)
        ->assertJsonPath('product_match_mode', FreeGiftProductMatchMode::All->value)
        ->assertJsonPath('gift_product_ids.0', $newGiftProduct->getKey());

    assertDatabaseHas('free_gifts', [
        'id' => $freeGift->getKey(),
        'name' => 'Updated Campaign',
        'selection_mode' => FreeGiftSelectionMode::Single->value,
        'product_match_mode' => FreeGiftProductMatchMode::All->value,
    ]);
    assertDatabaseMissing('free_gift_user', [
        'free_gift_id' => $freeGift->getKey(),
        'user_id' => $oldUser->getKey(),
    ]);
    assertDatabaseMissing('free_gift_qualifying_product', [
        'free_gift_id' => $freeGift->getKey(),
        'product_id' => $oldQualifyingProduct->getKey(),
    ]);
    assertDatabaseMissing('free_gift_product', [
        'free_gift_id' => $freeGift->getKey(),
        'product_id' => $oldGiftProduct->getKey(),
    ]);
    assertDatabaseHas('free_gift_product', [
        'free_gift_id' => $freeGift->getKey(),
        'product_id' => $newGiftProduct->getKey(),
    ]);

    deleteJson($prefix . '/free_gifts/' . $freeGift->getKey())
        ->assertNoContent();

    expect(FreeGift::withTrashed()->find($freeGift->getKey()))
        ->not->toBeNull()
        ->and(FreeGift::withTrashed()->find($freeGift->getKey())?->trashed())
        ->toBeTrue();
});
