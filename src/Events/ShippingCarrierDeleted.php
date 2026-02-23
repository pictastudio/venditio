<?php

namespace PictaStudio\Venditio\Events;

use PictaStudio\Venditio\Models\ShippingCarrier;

class ShippingCarrierDeleted
{
    public function __construct(
        public readonly ShippingCarrier $shippingCarrier,
    ) {}
}
