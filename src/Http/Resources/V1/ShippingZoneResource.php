<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class ShippingZoneResource extends JsonResource
{
    use CanTransformAttributes;
    use HasAttributesToExclude;

    public function toArray(Request $request)
    {
        return $this->applyAttributesTransformation(
            collect($this->resolveResourceAttributes())
                ->except($this->getAttributesToExclude())
                ->map(fn (mixed $value, string $key) => (
                    $this->mutateAttributeBasedOnCast($key, $value)
                ))
                ->merge($this->getRelationshipsToInclude())
                ->toArray()
        );
    }

    protected function getRelationshipsToInclude(): array
    {
        return [
            'country_ids' => $this->whenLoaded('countries', fn () => $this->countries->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()),
            'region_ids' => $this->whenLoaded('regions', fn () => $this->regions->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()),
            'province_ids' => $this->whenLoaded('provinces', fn () => $this->provinces->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()),
            'countries' => CountryResource::collection($this->whenLoaded('countries')),
            'regions' => RegionResource::collection($this->whenLoaded('regions')),
            'provinces' => ProvinceResource::collection($this->whenLoaded('provinces')),
            'shipping_method_zones' => ShippingMethodZoneResource::collection($this->whenLoaded('shippingMethodZones')),
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            //
        ];
    }
}
