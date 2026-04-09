<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};
use PictaStudio\Venditio\Http\Resources\Traits\ResolvesModelResource;

class AddressResource extends JsonResource
{
    use CanTransformAttributes;
    use HasAttributesToExclude;
    use ResolvesModelResource;

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
            'addressable' => $this->whenLoaded(
                'addressable',
                fn () => filled($this->resource->addressable)
                    ? $this->resolveResourceForModel($this->resource->addressable)
                    : null
            ),
            'country' => CountryResource::make($this->whenLoaded('country')),
            'province' => ProvinceResource::make($this->whenLoaded('province')),
        ];
    }

    protected function exclude(): array
    {
        return [
            'addressable_type',
            'addressable_id',
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            //
        ];
    }
}
