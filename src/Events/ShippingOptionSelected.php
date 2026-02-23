<?php

namespace PictaStudio\Venditio\Events;

use Illuminate\Database\Eloquent\Model;

class ShippingOptionSelected
{
    public function __construct(
        public readonly Model $cart,
        public readonly array $quote,
    ) {}
}
