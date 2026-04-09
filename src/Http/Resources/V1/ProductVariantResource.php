<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class ProductVariantResource extends JsonResource
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
            'product_type' => ProductTypeResource::make($this->whenLoaded('productType')),
            'product_variant_options' => ProductVariantOptionResource::collection($this->whenLoaded('productVariantOptions')),
            'options' => ProductVariantOptionResource::collection($this->whenLoaded('productVariantOptions')),
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            //
        ];
    }
}
