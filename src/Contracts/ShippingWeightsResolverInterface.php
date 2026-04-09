<?php

namespace PictaStudio\Venditio\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ShippingWeightsResolverInterface
{
    public function resolve(Model $cart, ?Model $shippingMethod = null): array;
}
