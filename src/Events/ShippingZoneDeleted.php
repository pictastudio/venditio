<?php

namespace PictaStudio\Venditio\Events;

use PictaStudio\Venditio\Models\ShippingZone;

class ShippingZoneDeleted
{
    public function __construct(
        public readonly ShippingZone $shippingZone,
    ) {}
}
