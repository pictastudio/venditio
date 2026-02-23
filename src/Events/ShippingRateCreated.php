<?php

namespace PictaStudio\Venditio\Events;

use PictaStudio\Venditio\Models\ShippingRate;

class ShippingRateCreated
{
    public function __construct(
        public readonly ShippingRate $shippingRate,
    ) {}
}
