<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class ProductTypeResource extends JsonResource
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
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'product_variants' => ProductVariantResource::collection($this->whenLoaded('productVariants')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('productVariants')),
            'product_custom_fields' => ProductCustomFieldResource::collection($this->whenLoaded('productCustomFields')),
            'discounts' => DiscountResource::collection($this->whenLoaded('discounts')),
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            //
        ];
    }
}
