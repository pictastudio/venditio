<?php

namespace PictaStudio\Venditio\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ShippingQuoteCalculatorInterface
{
    /**
     * @return Collection<int, array{
     *     shipping_rate_id: int,
     *     shipping_carrier_id: int,
     *     shipping_zone_id: int,
     *     actual_weight_kg: float,
     *     volumetric_weight_kg: float,
     *     chargeable_weight_kg: float,
     *     base_fee: float,
     *     tier_fee: float,
     *     amount: float,
     *     carrier: array{id:int,code:string,name:string},
     *     zone: array{id:int,code:string,name:string}
     * }>
     */
    public function calculateForCart(Model $cart): Collection;
}
