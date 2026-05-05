<?php

namespace PictaStudio\Venditio\Actions\Wishlists;

use Illuminate\Database\Eloquent\Model;

class AddWishlistItem
{
    use CreatesOrUpdatesWishlistItems;

    public function handle(Model $wishlist, array $payload): Model
    {
        return $this->addOrRestoreWishlistItem($wishlist, $payload);
    }
}
