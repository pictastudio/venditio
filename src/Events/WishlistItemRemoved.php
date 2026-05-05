<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class WishlistItemRemoved
{
    public function __construct(
        public readonly Model $wishlistItem,
    ) {}
}
