<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Event, Gate};
use PictaStudio\Venditio\Events\{WishlistCreated, WishlistDeleted, WishlistItemAdded, WishlistItemRemoved, WishlistItemUpdated, WishlistUpdated};
use PictaStudio\Venditio\Models\{Product, User, Wishlist, WishlistItem};

use function Pest\Laravel\{assertDatabaseHas, assertSoftDeleted, deleteJson, getJson, patchJson, postJson};

uses(RefreshDatabase::class);

function createWishlistProduct(): Product
{
    return Product::factory()->create([
        'brand_id' => null,
        'slug' => fake()->unique()->slug(),
        'active' => true,
        'visible_from' => null,
        'visible_until' => null,
    ]);
}

it('creates a wishlist with products and dispatches events', function () {
    Event::fake([WishlistCreated::class, WishlistItemAdded::class]);

    $user = User::factory()->create();
    $firstProduct = createWishlistProduct();
    $secondProduct = createWishlistProduct();

    $response = postJson(config('venditio.routes.api.v1.prefix') . '/wishlists?include=items,products,products_count,user', [
        'user_id' => $user->getKey(),
        'name' => 'Birthday ideas',
        'description' => 'Products to buy later',
        'is_default' => true,
        'metadata' => ['channel' => 'api'],
        'product_ids' => [$firstProduct->getKey(), $secondProduct->getKey()],
    ])->assertCreated()
        ->assertJsonPath('user_id', $user->getKey())
        ->assertJsonPath('name', 'Birthday ideas')
        ->assertJsonPath('slug', 'birthday-ideas')
        ->assertJsonPath('is_default', true)
        ->assertJsonPath('products_count', 2)
        ->assertJsonPath('items.0.product_id', $firstProduct->getKey())
        ->assertJsonPath('products.0.id', $firstProduct->getKey())
        ->assertJsonPath('user.id', $user->getKey());

    $wishlistId = $response->json('id');

    assertDatabaseHas('wishlists', [
        'id' => $wishlistId,
        'user_id' => $user->getKey(),
        'metadata' => json_encode(['channel' => 'api']),
    ]);
    assertDatabaseHas('wishlist_items', [
        'wishlist_id' => $wishlistId,
        'product_id' => $firstProduct->getKey(),
    ]);
    assertDatabaseHas('wishlist_items', [
        'wishlist_id' => $wishlistId,
        'product_id' => $secondProduct->getKey(),
    ]);

    Event::assertDispatched(WishlistCreated::class);
    Event::assertDispatched(WishlistItemAdded::class, 2);
});

it('lists and filters wishlists by user_id', function () {
    $matchingUser = User::factory()->create();
    $otherUser = User::factory()->create();
    $matchingWishlist = Wishlist::factory()->create([
        'user_id' => $matchingUser->getKey(),
        'name' => 'Matching',
    ]);
    Wishlist::factory()->create([
        'user_id' => $otherUser->getKey(),
        'name' => 'Other',
    ]);

    getJson(config('venditio.routes.api.v1.prefix') . '/wishlists?all=1&user_id=' . $matchingUser->getKey())
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $matchingWishlist->getKey());
});

it('updates wishlist fields, syncs products, and clears previous default for the user', function () {
    Event::fake([WishlistUpdated::class, WishlistItemAdded::class]);

    $user = User::factory()->create();
    $previousDefault = Wishlist::factory()->create([
        'user_id' => $user->getKey(),
        'is_default' => true,
    ]);
    $wishlist = Wishlist::factory()->create([
        'user_id' => $user->getKey(),
        'is_default' => false,
    ]);
    $removedProduct = createWishlistProduct();
    $keptProduct = createWishlistProduct();
    $addedProduct = createWishlistProduct();

    $wishlist->items()->create(['product_id' => $removedProduct->getKey()]);
    $wishlist->items()->create(['product_id' => $keptProduct->getKey()]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/wishlists/{$wishlist->getKey()}?include=items,products_count", [
        'name' => 'Updated wishlist',
        'is_default' => true,
        'product_ids' => [$keptProduct->getKey(), $addedProduct->getKey()],
    ])->assertOk()
        ->assertJsonPath('name', 'Updated wishlist')
        ->assertJsonPath('is_default', true)
        ->assertJsonPath('products_count', 2);

    expect($previousDefault->refresh()->is_default)->toBeFalse()
        ->and($wishlist->refresh()->is_default)->toBeTrue();

    assertSoftDeleted('wishlist_items', [
        'wishlist_id' => $wishlist->getKey(),
        'product_id' => $removedProduct->getKey(),
    ]);
    assertDatabaseHas('wishlist_items', [
        'wishlist_id' => $wishlist->getKey(),
        'product_id' => $keptProduct->getKey(),
        'deleted_at' => null,
    ]);
    assertDatabaseHas('wishlist_items', [
        'wishlist_id' => $wishlist->getKey(),
        'product_id' => $addedProduct->getKey(),
        'deleted_at' => null,
    ]);

    Event::assertDispatched(WishlistUpdated::class);
});

it('validates wishlist ownership and product ids', function () {
    postJson(config('venditio.routes.api.v1.prefix') . '/wishlists', [
        'user_id' => 999_999,
        'name' => 'Broken',
        'product_ids' => [999_999],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id', 'product_ids.0']);
});

it('adds, updates, removes, and restores wishlist items', function () {
    Event::fake([WishlistItemAdded::class, WishlistItemUpdated::class, WishlistItemRemoved::class]);

    $wishlist = Wishlist::factory()->create();
    $product = createWishlistProduct();

    $itemId = postJson(config('venditio.routes.api.v1.prefix') . "/wishlists/{$wishlist->getKey()}/items?include=product", [
        'product_id' => $product->getKey(),
        'notes' => 'Buy on sale',
        'sort_order' => 10,
    ])->assertCreated()
        ->assertJsonPath('product_id', $product->getKey())
        ->assertJsonPath('notes', 'Buy on sale')
        ->assertJsonPath('sort_order', 10)
        ->assertJsonPath('product.id', $product->getKey())
        ->json('id');

    patchJson(config('venditio.routes.api.v1.prefix') . "/wishlists/{$wishlist->getKey()}/items/{$itemId}", [
        'notes' => 'Updated note',
        'sort_order' => 20,
    ])->assertOk()
        ->assertJsonPath('notes', 'Updated note')
        ->assertJsonPath('sort_order', 20);

    deleteJson(config('venditio.routes.api.v1.prefix') . "/wishlists/{$wishlist->getKey()}/items/{$itemId}")
        ->assertNoContent();

    assertSoftDeleted('wishlist_items', ['id' => $itemId]);

    postJson(config('venditio.routes.api.v1.prefix') . "/wishlists/{$wishlist->getKey()}/items", [
        'product_id' => $product->getKey(),
    ])->assertOk()
        ->assertJsonPath('id', $itemId);

    expect(WishlistItem::query()->find($itemId))->not->toBeNull();

    Event::assertDispatched(WishlistItemAdded::class, 2);
    Event::assertDispatched(WishlistItemUpdated::class);
    Event::assertDispatched(WishlistItemRemoved::class);
});

it('rejects updating or removing a wishlist item from another wishlist', function () {
    $wishlist = Wishlist::factory()->create();
    $otherWishlist = Wishlist::factory()->create();
    $item = WishlistItem::factory()->create([
        'wishlist_id' => $otherWishlist->getKey(),
        'product_id' => createWishlistProduct()->getKey(),
    ]);

    patchJson(config('venditio.routes.api.v1.prefix') . "/wishlists/{$wishlist->getKey()}/items/{$item->getKey()}", [
        'notes' => 'Nope',
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'The wishlist item does not belong to the provided wishlist.');

    deleteJson(config('venditio.routes.api.v1.prefix') . "/wishlists/{$wishlist->getKey()}/items/{$item->getKey()}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'The wishlist item does not belong to the provided wishlist.');
});

it('soft deletes wishlists and their items', function () {
    Event::fake([WishlistDeleted::class]);

    $wishlist = Wishlist::factory()->create();
    $item = WishlistItem::factory()->create([
        'wishlist_id' => $wishlist->getKey(),
        'product_id' => createWishlistProduct()->getKey(),
    ]);

    deleteJson(config('venditio.routes.api.v1.prefix') . "/wishlists/{$wishlist->getKey()}")
        ->assertNoContent();

    assertSoftDeleted('wishlists', ['id' => $wishlist->getKey()]);
    assertSoftDeleted('wishlist_items', ['id' => $item->getKey()]);
    Event::assertDispatched(WishlistDeleted::class);
});

it('uses wishlist policies when configured', function () {
    config(['venditio.authorize_using_policies' => true]);
    Gate::policy(Wishlist::class, DenyWishlistPolicy::class);

    getJson(config('venditio.routes.api.v1.prefix') . '/wishlists')
        ->assertForbidden();
});

class DenyWishlistPolicy
{
    public function viewAny(?Authenticatable $user): bool
    {
        return false;
    }
}
