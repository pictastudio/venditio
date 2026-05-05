<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class WishlistDeleted
{
    public function __construct(
        public readonly Model $wishlist,
    ) {}
}
