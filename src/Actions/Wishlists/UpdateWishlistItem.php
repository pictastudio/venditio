<?php

namespace PictaStudio\Venditio\Actions\Wishlists;

use Illuminate\Database\Eloquent\Model;
use PictaStudio\Venditio\Events\WishlistItemUpdated;

class UpdateWishlistItem
{
    public function handle(Model $wishlistItem, array $payload): Model
    {
        $wishlistItem->fill($payload);
        $wishlistItem->save();

        event(new WishlistItemUpdated($wishlistItem));

        return $wishlistItem->refresh();
    }
}
