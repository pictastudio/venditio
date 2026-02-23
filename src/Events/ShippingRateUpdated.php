<?php

namespace PictaStudio\Venditio\Events;

use PictaStudio\Venditio\Models\ShippingRate;

class ShippingRateUpdated
{
    public function __construct(
        public readonly ShippingRate $shippingRate,
    ) {}
}
