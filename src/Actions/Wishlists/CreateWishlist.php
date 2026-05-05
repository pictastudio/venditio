<?php

namespace PictaStudio\Venditio\Actions\Wishlists;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\{Arr, Str};
use Illuminate\Support\Facades\DB;
use PictaStudio\Venditio\Events\WishlistCreated;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreateWishlist
{
    use CreatesOrUpdatesWishlistItems;

    public function handle(array $payload): Model
    {
        return DB::transaction(function () use ($payload): Model {
            $productIdsProvided = array_key_exists('product_ids', $payload);
            $productIds = Arr::pull($payload, 'product_ids', []);

            if ((bool) ($payload['is_default'] ?? false)) {
                $this->clearDefaultWishlists((int) $payload['user_id']);
            }

            $payload['slug'] ??= $this->generateUniqueSlug($payload['name'], (int) $payload['user_id']);

            $wishlist = resolve_model('wishlist')::create($payload);

            if ($productIdsProvided) {
                $this->syncWishlistProducts($wishlist, $productIds ?? []);
            }

            event(new WishlistCreated($wishlist));

            return $wishlist->refresh();
        });
    }

    private function clearDefaultWishlists(int $userId): void
    {
        resolve_model('wishlist')::query()
            ->where('user_id', $userId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function generateUniqueSlug(string $name, int $userId): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (
            resolve_model('wishlist')::query()
                ->where('user_id', $userId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
