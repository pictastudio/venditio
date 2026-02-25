<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use PictaStudio\Venditio\Contracts\ProductPriceResolverInterface;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class ProductResource extends JsonResource
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
                ->merge($this->getRelationshipsToInclude($request))
                ->toArray()
        );
    }

    protected function getRelationshipsToInclude(Request $request): array
    {
        $includes = $this->resolveRequestedIncludes($request);
        $shouldIncludePriceLists = in_array('price_lists', $includes, true);
        $shouldIncludeVariants = in_array('variants', $includes, true) && blank($this->parent_id);
        $shouldIncludeVariantsOptionsTable = in_array('variants_options_table', $includes, true) && blank($this->parent_id);

        return [
            'price_calculated' => $this->resolveCalculatedPrice(),
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'product_type' => ProductTypeResource::make($this->whenLoaded('productType')),
            'tax_class' => TaxClassResource::make($this->whenLoaded('taxClass')),
            'parent' => self::make($this->whenLoaded('parent')),
            'categories' => ProductCategoryResource::collection($this->whenLoaded('categories')),
            'price_lists_relation' => PriceListResource::collection($this->whenLoaded('priceLists')),
            'price_list_prices' => PriceListPriceResource::collection($this->whenLoaded('priceListPrices')),
            'variant_options' => ProductVariantOptionResource::collection($this->whenLoaded('variantOptions')),
            'inventory' => InventoryResource::make($this->whenLoaded('inventory')),
            'discounts' => DiscountResource::collection($this->whenLoaded('discounts')),
            'price_lists' => $this->when(
                $shouldIncludePriceLists && $this->resource->relationLoaded('priceListPrices'),
                fn () => collect($this->resource->getRelation('priceListPrices'))
                    ->map(fn ($priceListPrice): array => [
                        'id' => $priceListPrice->getKey(),
                        'product_id' => $priceListPrice->product_id,
                        'price_list_id' => $priceListPrice->price_list_id,
                        'price' => $priceListPrice->price,
                        'purchase_price' => $priceListPrice->purchase_price,
                        'price_includes_tax' => (bool) $priceListPrice->price_includes_tax,
                        'is_default' => (bool) $priceListPrice->is_default,
                        'metadata' => $priceListPrice->metadata,
                        'price_list' => $priceListPrice->relationLoaded('priceList')
                            ? [
                                'id' => $priceListPrice->priceList?->getKey(),
                                'name' => $priceListPrice->priceList?->name,
                                'code' => $priceListPrice->priceList?->code,
                                'active' => (bool) ($priceListPrice->priceList?->active ?? true),
                                'description' => $priceListPrice->priceList?->description,
                                'metadata' => $priceListPrice->priceList?->metadata,
                            ]
                            : null,
                    ])
                    ->values()
                    ->all()
            ),
            'variants' => $this->when(
                $shouldIncludeVariants,
                fn () => self::collection($this->whenLoaded('variants'))
            ),
            'variants_relation' => self::collection($this->whenLoaded('variants')),
            'variants_options_table' => $this->when(
                $shouldIncludeVariantsOptionsTable,
                fn () => $this->buildVariantsOptionsTable()
            ),
        ];
    }

    protected function resolveCalculatedPrice(): array
    {
        $resolved = app(ProductPriceResolverInterface::class)->resolve($this->resource);

        return [
            'price' => (float) ($resolved['unit_price'] ?? 0),
            'purchase_price' => isset($resolved['purchase_price']) ? (float) $resolved['purchase_price'] : null,
            'price_includes_tax' => (bool) ($resolved['price_includes_tax'] ?? false),
            'price_list' => $resolved['price_list'] ?? null,
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            'images' => function (mixed $images) {
                if (is_string($images)) {
                    $images = json_decode($images, true) ?: [];
                }

                if (!is_array($images)) {
                    return [];
                }

                return collect($images)
                    ->map(fn (array $image) => [
                        'alt' => $image['alt'],
                        'src' => $this->getImageAssetUrl($image['src']),
                    ])
                    ->toArray();
            },
            'files' => function (mixed $files) {
                if (is_string($files)) {
                    $files = json_decode($files, true) ?: [];
                }

                if (!is_array($files)) {
                    return [];
                }

                return collect($files)
                    ->map(fn (array $file) => [
                        'name' => $file['name'],
                        'src' => $this->getImageAssetUrl($file['src']),
                    ])
                    ->toArray();
            },
        ];
    }

    protected function resolveRequestedIncludes(Request $request): array
    {
        $rawIncludes = $request->query('include', []);

        return collect(is_array($rawIncludes) ? $rawIncludes : [$rawIncludes])
            ->flatMap(fn (mixed $include) => is_string($include) ? explode(',', $include) : [])
            ->map(fn (string $include) => mb_trim($include))
            ->filter(fn (string $include) => filled($include))
            ->unique()
            ->values()
            ->all();
    }

    protected function buildVariantsOptionsTable(): array
    {
        if (!$this->resource->relationLoaded('variants')) {
            return [];
        }

        /** @var Collection<int, array{id:int, product_type_id:mixed, name:mixed, sort_order:mixed, values:array<int, array{id:int, value:mixed}>}> $table */
        $table = $this->resource->variants
            ->flatMap(fn ($variant) => $variant->relationLoaded('variantOptions') ? $variant->variantOptions : collect())
            ->filter(fn ($option) => $option->relationLoaded('productVariant') && filled($option->productVariant))
            ->groupBy(fn ($option) => $option->productVariant->getKey())
            ->map(function (Collection $options): array {
                $productVariant = $options->first()->productVariant;

                return [
                    'id' => $productVariant->getKey(),
                    'product_type_id' => $productVariant->product_type_id,
                    'name' => $productVariant->name,
                    'sort_order' => $productVariant->sort_order,
                    'values' => $options
                        ->unique(fn ($option) => $option->getKey())
                        ->sortBy([
                            ['sort_order', 'asc'],
                            ['id', 'asc'],
                        ])
                        ->values()
                        ->map(fn ($option): array => [
                            'id' => $option->getKey(),
                            'value' => $option->name,
                            'image' => $this->getImageAssetUrl($option->image),
                            'hex_color' => $option->hex_color,
                        ])
                        ->all(),
                ];
            })
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        return $table->toArray();
    }
}
