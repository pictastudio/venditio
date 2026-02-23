<?php

namespace PictaStudio\Venditio\Events;

use PictaStudio\Venditio\Models\ShippingCarrier;

class ShippingCarrierCreated
{
    public function __construct(
        public readonly ShippingCarrier $shippingCarrier,
    ) {}
}
