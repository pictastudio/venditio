<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class WishlistUpdated
{
    public function __construct(
        public readonly Model $wishlist,
    ) {}
}
