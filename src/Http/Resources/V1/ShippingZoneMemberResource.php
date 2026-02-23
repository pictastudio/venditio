<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

class ShippingZoneMemberResource extends GenericModelResource
{
    protected function getRelationshipsToInclude(): array
    {
        return [
            'shipping_zone' => $this->whenLoaded(
                'shippingZone',
                fn () => ShippingZoneResource::make($this->shippingZone)
            ),
            'zoneable' => $this->whenLoaded(
                'zoneable',
                fn () => GenericModelResource::make($this->zoneable)
            ),
        ];
    }
}
