<?php

namespace PictaStudio\Venditio\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ChargeableWeightCalculatorInterface
{
    /**
     * @param  Collection<int, Model>  $lines
     * @return array{actual_weight_kg: float, volumetric_weight_kg: float, chargeable_weight_kg: float}
     */
    public function calculate(Collection $lines, Model $shippingCarrier): array;
}
