<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class CountryResource extends JsonResource
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
            'tax_classes' => TaxClassResource::collection($this->whenLoaded('taxClasses')),
            'currency' => CurrencyResource::make($this->whenLoaded('currency')),
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'regions' => RegionResource::collection($this->whenLoaded('regions')),
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            //
        ];
    }
}
