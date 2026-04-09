<?php

namespace PictaStudio\Venditio\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ShippingZoneResolverInterface
{
    public function resolve(Model $cart, Model $shippingMethod): array;
}
