<?php

namespace PictaStudio\Venditio\Actions\Wishlists;

use Illuminate\Database\Eloquent\Model;
use PictaStudio\Venditio\Events\WishlistItemAdded;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

trait CreatesOrUpdatesWishlistItems
{
    /**
     * @param  array<int, mixed>  $productIds
     */
    protected function syncWishlistProducts(Model $wishlist, array $productIds): void
    {
        $productIds = collect($productIds)
            ->map(fn (mixed $productId): int => (int) $productId)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            $wishlist->items()->delete();

            return;
        }

        $wishlist->items()
            ->whereNotIn('product_id', $productIds->all())
            ->delete();

        foreach ($productIds as $index => $productId) {
            $this->addOrRestoreWishlistItem($wishlist, [
                'product_id' => $productId,
                'sort_order' => $index,
            ]);
        }
    }

    protected function addOrRestoreWishlistItem(Model $wishlist, array $payload): Model
    {
        $wishlistItemModel = resolve_model('wishlist_item');

        $item = $wishlistItemModel::withTrashed()
            ->where('wishlist_id', $wishlist->getKey())
            ->where('product_id', $payload['product_id'])
            ->first();

        $wasNew = $item === null || $item->trashed();

        if ($item === null) {
            $item = new $wishlistItemModel([
                'wishlist_id' => $wishlist->getKey(),
                'product_id' => $payload['product_id'],
            ]);
        }

        $item->fill($payload);

        if ($item->trashed()) {
            $item->restore();
        }

        $item->save();

        if ($wasNew) {
            event(new WishlistItemAdded($item));
        }

        return $item->refresh();
    }
}
