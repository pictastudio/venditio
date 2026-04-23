<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class ProductCollectionResource extends JsonResource
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
            'discounts' => DiscountResource::collection($this->whenLoaded('discounts')),
            'valid_discounts' => DiscountResource::collection($this->whenLoaded('validDiscounts')),
            'expired_discounts' => DiscountResource::collection($this->whenLoaded('expiredDiscounts')),
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            'images' => fn (mixed $images): array => $this->transformCatalogImageCollection($images),
        ];
    }
}
