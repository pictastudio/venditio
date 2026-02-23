<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShippingQuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'shipping_rate_id' => (int) data_get($this->resource, 'shipping_rate_id'),
            'shipping_carrier_id' => (int) data_get($this->resource, 'shipping_carrier_id'),
            'shipping_zone_id' => (int) data_get($this->resource, 'shipping_zone_id'),
            'actual_weight_kg' => (float) data_get($this->resource, 'actual_weight_kg', 0),
            'volumetric_weight_kg' => (float) data_get($this->resource, 'volumetric_weight_kg', 0),
            'chargeable_weight_kg' => (float) data_get($this->resource, 'chargeable_weight_kg', 0),
            'base_fee' => (float) data_get($this->resource, 'base_fee', 0),
            'tier_fee' => (float) data_get($this->resource, 'tier_fee', 0),
            'amount' => (float) data_get($this->resource, 'amount', 0),
            'carrier' => data_get($this->resource, 'carrier'),
            'zone' => data_get($this->resource, 'zone'),
        ];
    }
}
