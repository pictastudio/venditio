<?php

namespace PictaStudio\Venditio\Events;

use PictaStudio\Venditio\Models\ShippingZone;

class ShippingZoneUpdated
{
    public function __construct(
        public readonly ShippingZone $shippingZone,
    ) {}
}
