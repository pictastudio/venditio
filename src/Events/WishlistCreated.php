<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class WishlistCreated
{
    public function __construct(
        public readonly Model $wishlist,
    ) {}
}
