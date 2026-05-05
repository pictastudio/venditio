<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class WishlistItemAdded
{
    public function __construct(
        public readonly Model $wishlistItem,
    ) {}
}
