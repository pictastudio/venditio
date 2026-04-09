<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class TagResource extends JsonResource
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
            'brands' => BrandResource::collection($this->whenLoaded('brands')),
            'product_categories' => ProductCategoryResource::collection($this->whenLoaded('productCategories')),
            'tags' => self::collection($this->whenLoaded('tags')),
            'product_type' => ProductTypeResource::make($this->whenLoaded('productType')),
            'parent' => self::make($this->whenLoaded('parent')),
            'children' => self::collection($this->whenLoaded('children')),
            'descendants' => self::collection($this->whenLoaded('descendants')),
            'discounts' => DiscountResource::collection($this->whenLoaded('discounts')),
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            'img_thumb' => function (mixed $image): ?array {
                if (is_string($image)) {
                    $image = json_decode($image, true);
                }

                if (!is_array($image)) {
                    return null;
                }

                return [
                    'name' => data_get($image, 'name'),
                    'alt' => data_get($image, 'alt'),
                    'src' => $this->getImageAssetUrl(data_get($image, 'src')),
                ];
            },
            'img_cover' => function (mixed $image): ?array {
                if (is_string($image)) {
                    $image = json_decode($image, true);
                }

                if (!is_array($image)) {
                    return null;
                }

                return [
                    'name' => data_get($image, 'name'),
                    'alt' => data_get($image, 'alt'),
                    'src' => $this->getImageAssetUrl(data_get($image, 'src')),
                ];
            },
        ];
    }
}
