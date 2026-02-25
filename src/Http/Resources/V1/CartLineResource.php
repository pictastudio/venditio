<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class CartLineResource extends JsonResource
{
    use CanTransformAttributes;
    use HasAttributesToExclude;

    public function toArray(Request $request)
    {
        return $this->applyAttributesTransformation(
            collect(parent::toArray($request))
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
            'cart' => CartResource::make($this->whenLoaded('cart')),
            'product' => ProductResource::make($this->whenLoaded('product')),
            'currency' => CurrencyResource::make($this->whenLoaded('currency')),
            'discount' => DiscountResource::make($this->whenLoaded('discount')),
            'discounts' => DiscountResource::collection($this->whenLoaded('discounts')),
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            'product_data.images' => fn (?array $images) => (
                collect($images)
                    ->map(fn (array $image) => [
                        'alt' => $image['alt'],
                        'src' => $this->getImageAssetUrl($image['src']),
                    ])
                    ->toArray()
            ),
            'product_data.files' => fn (?array $files) => (
                collect($files)
                    ->map(fn (array $file) => [
                        'name' => $file['name'],
                        'src' => $this->getImageAssetUrl($file['src']),
                    ])
                    ->toArray()
            ),
        ];
    }
}
