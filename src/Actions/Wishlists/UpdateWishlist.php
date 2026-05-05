<?php

namespace PictaStudio\Venditio\Actions\Wishlists;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PictaStudio\Venditio\Events\WishlistUpdated;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateWishlist
{
    use CreatesOrUpdatesWishlistItems;

    public function handle(Model $wishlist, array $payload): Model
    {
        return DB::transaction(function () use ($wishlist, $payload): Model {
            $productIdsProvided = array_key_exists('product_ids', $payload);
            $productIds = Arr::pull($payload, 'product_ids', []);
            $targetUserId = (int) ($payload['user_id'] ?? $wishlist->getAttribute('user_id'));
            $willBeDefault = (bool) ($payload['is_default'] ?? $wishlist->getAttribute('is_default'));

            if ($willBeDefault) {
                $this->clearDefaultWishlists($targetUserId, $wishlist);
            }

            $wishlist->fill($payload);
            $wishlist->save();

            if ($productIdsProvided) {
                $this->syncWishlistProducts($wishlist, $productIds ?? []);
            }

            event(new WishlistUpdated($wishlist));

            return $wishlist->refresh();
        });
    }

    private function clearDefaultWishlists(int $userId, Model $wishlist): void
    {
        resolve_model('wishlist')::query()
            ->where('user_id', $userId)
            ->whereKeyNot($wishlist->getKey())
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
