<?php

namespace PictaStudio\Venditio\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ShippingFeeCalculatorInterface
{
    public function calculate(string $strategy, Model $cart, ?Model $shippingMethod = null, ?Model $shippingMethodZone = null): float;
}
