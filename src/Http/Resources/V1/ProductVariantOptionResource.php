<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};
use PictaStudio\Venditio\Models\Product;
use PictaStudio\Venditio\Support\ProductMedia;

class ProductVariantOptionResource extends JsonResource
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
                ->except(['image'])
                ->merge([
                    'images' => $this->resolveSharedImages(),
                ])
                ->merge($this->getRelationshipsToInclude())
                ->toArray()
        );
    }

    protected function getRelationshipsToInclude(): array
    {
        return [
            'product_variant' => ProductVariantResource::make($this->whenLoaded('productVariant')),
            'products' => ProductResource::collection($this->whenLoaded('products')),
        ];
    }

    protected function transformAttributes(): array
    {
        return [];
    }

    protected function resolveSharedImages(): array
    {
        $variantProducts = $this->resource->relationLoaded('variantProducts')
            ? $this->resource->variantProducts
            : $this->resource->variantProducts()->get();

        $sharedImages = $variantProducts
            ->flatMap(function (Product $product): array {
                return ProductMedia::normalizeCollection($product->getAttribute('images'), isImage: true);
            })
            ->filter(function (array $image): bool {
                return (bool) Arr::get($image, 'shared_from_variant_option', false)
                    && str_contains((string) Arr::get($image, 'src'), $this->sharedImagePathFragment());
            })
            ->unique(fn (array $image): string => (string) Arr::get($image, 'src'))
            ->values()
            ->all();

        return $this->transformProductMediaCollection($sharedImages, true);
    }

    protected function sharedImagePathFragment(): string
    {
        return "/variant_options/{$this->resource->getKey()}/images/";
    }
}
