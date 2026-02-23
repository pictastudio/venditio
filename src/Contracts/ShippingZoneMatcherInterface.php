<?php

namespace PictaStudio\Venditio\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ShippingZoneMatcherInterface
{
    public function match(array $shippingAddress): ?Model;
}
