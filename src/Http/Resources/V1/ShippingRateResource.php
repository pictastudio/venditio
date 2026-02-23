<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

class ShippingRateResource extends GenericModelResource
{
    protected function getRelationshipsToInclude(): array
    {
        return [
            'shipping_carrier' => $this->whenLoaded(
                'shippingCarrier',
                fn () => ShippingCarrierResource::make($this->shippingCarrier)
            ),
            'shipping_zone' => $this->whenLoaded(
                'shippingZone',
                fn () => ShippingZoneResource::make($this->shippingZone)
            ),
            'tiers' => ShippingRateTierResource::collection($this->whenLoaded('tiers')),
        ];
    }
}
