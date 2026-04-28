<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\{Arr, Collection};
use PictaStudio\Venditio\Actions\Taxes\{ExtractTaxFromGrossPrice, ResolveTaxRate};
use PictaStudio\Venditio\Contracts\{DiscountCalculatorInterface, ProductPriceResolverInterface};
use PictaStudio\Venditio\Discounts\DiscountContext;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};
use PictaStudio\Venditio\Support\ProductMedia;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ProductResource extends JsonResource
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
            'price_calculated' => $this->resolveCalculatedPrice($request),
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'product_type' => ProductTypeResource::make($this->whenLoaded('productType')),
            'tax_class' => TaxClassResource::make($this->whenLoaded('taxClass')),
            'parent' => self::make($this->whenLoaded('parent')),
            'categories' => ProductCategoryResource::collection($this->whenLoaded('categories')),
            'collections' => ProductCollectionResource::collection($this->whenLoaded('collections')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'price_lists_relation' => PriceListResource::collection($this->whenLoaded('priceLists')),
            'price_list_prices' => PriceListPriceResource::collection($this->whenLoaded('priceListPrices')),
            'variant_options' => ProductVariantOptionResource::collection($this->whenLoaded('variantOptions')),
            'inventory' => InventoryResource::make($this->whenLoaded('inventory')),
            'discounts' => DiscountResource::collection($this->whenLoaded('discounts')),
            'valid_discounts' => DiscountResource::collection($this->whenLoaded('validDiscounts')),
            'expired_discounts' => DiscountResource::collection($this->whenLoaded('expiredDiscounts')),
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
            'variants_options_table' => $this->when(
                $shouldIncludeVariantsOptionsTable,
                fn () => $this->buildVariantsOptionsTable()
            ),
        ];
    }

    protected function resolveCalculatedPrice(Request $request): array
    {
        $includes = $this->resolveRequestedIncludes($request);
        $shouldIncludePriceBreakdown = in_array('price_breakdown', $includes, true);
        $resolved = app(ProductPriceResolverInterface::class)->resolve($this->resource);
        $baseUnitPrice = (float) ($resolved['unit_price'] ?? 0);
        $pricingPreview = $this->resolvePricingPreview($resolved, $request);
        $priceIncludesTax = (bool) ($resolved['price_includes_tax'] ?? false);
        // $taxRate = app(ResolveTaxRate::class)->handle(
        //     $this->resource->getAttribute('tax_class_id'),
        //     countryIso2: $this->resolveCountryIso2Header($request),
        // );
        // $baseTaxBreakdown = $this->resolveTaxBreakdown($baseUnitPrice, $taxRate, $priceIncludesTax);
        // $finalTaxBreakdown = $this->resolveTaxBreakdown($calculatedPriceFinal, $taxRate, $priceIncludesTax);

        $priceCalculated = [
            'price' => $baseUnitPrice,
            'price_final' => $pricingPreview['price_final'],
            'purchase_price' => isset($resolved['purchase_price']) ? (float) $resolved['purchase_price'] : null,
            'price_includes_tax' => $priceIncludesTax,
            // 'tax_rate' => $taxRate,
            // 'price_taxable' => $baseTaxBreakdown['taxable'],
            // 'price_tax' => $baseTaxBreakdown['tax'],
            // 'price_total' => $baseTaxBreakdown['total'],
            // 'price_final_taxable' => $finalTaxBreakdown['taxable'],
            // 'price_final_tax' => $finalTaxBreakdown['tax'],
            // 'price_final_total' => $finalTaxBreakdown['total'],
            'price_list' => $resolved['price_list'] ?? null,
        ];

        if ($shouldIncludePriceBreakdown) {
            $priceCalculated['price_source'] = $this->resolvePriceSource($resolved);
            $priceCalculated['discounts_applied'] = $pricingPreview['discounts_applied'];
        }

        return $priceCalculated;
    }

    protected function resolvePricingPreview(array $resolved, Request $request): array
    {
        $unitPrice = (float) ($resolved['unit_price'] ?? 0);
        $cartLineModelClass = resolve_model('cart_line');
        /** @var Model $previewLine */
        $previewLine = new $cartLineModelClass;

        $previewLine->fill([
            'product_id' => $this->resource->getKey(),
            'qty' => 1,
            'unit_price' => $unitPrice,
            'purchase_price' => $resolved['purchase_price'] ?? null,
            'product_data' => [
                'pricing' => [
                    'price_list' => $resolved['price_list'] ?? null,
                    'price_source' => $this->resolvePriceSource($resolved),
                ],
                'price_calculated' => [
                    'price' => $unitPrice,
                    'price_final' => $unitPrice,
                    'purchase_price' => isset($resolved['purchase_price']) ? (float) $resolved['purchase_price'] : null,
                    'price_includes_tax' => (bool) ($resolved['price_includes_tax'] ?? false),
                    'price_list' => $resolved['price_list'] ?? null,
                    'price_source' => $this->resolvePriceSource($resolved),
                ],
            ],
        ]);

        if ($this->resource instanceof Model) {
            $this->resource->loadMissing(['categories', 'collections', 'brand', 'productType', 'parent']);
            $previewLine->setRelation('product', $this->resource);
        }

        $user = $request->user();
        $context = DiscountContext::make(user: $user instanceof Model ? $user : null);
        $calculatedLine = app(DiscountCalculatorInterface::class)->apply($previewLine, $context);

        return [
            'price_final' => (float) ($calculatedLine->getAttribute('unit_final_price') ?? $unitPrice),
            'discounts_applied' => collect(
                data_get($calculatedLine->getAttribute('product_data'), 'price_calculated.discounts_applied', [])
            )
                ->filter(fn (mixed $entry) => is_array($entry))
                ->values()
                ->all(),
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            'images' => fn (mixed $images) => $this->transformProductMediaCollection($images, true),
            'files' => fn (mixed $files) => $this->transformProductMediaCollection($files, false),
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
                            'images' => $this->resolveVariantOptionImages($option),
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

    protected function resolveVariantOptionImages(mixed $option): array
    {
        $productId = $this->resource->getKey();
        $pathFragment = "products/{$productId}/variant_options/{$option->getKey()}/images/";

        $images = $this->resource->variants
            ->filter(fn ($variant): bool => (string) $variant->parent_id === (string) $productId)
            ->filter(fn ($variant): bool => $variant->relationLoaded('variantOptions')
                && $variant->variantOptions->contains(fn ($variantOption): bool => (
                    (string) $variantOption->getKey() === (string) $option->getKey()
                ))
            )
            ->flatMap(fn ($variant): array => ProductMedia::normalizeCollection(
                $variant->getAttribute('images'),
                isImage: true
            ))
            ->filter(fn (array $image): bool => (bool) Arr::get($image, 'shared_from_variant_option', false)
                && str_contains((string) Arr::get($image, 'src'), $pathFragment)
            )
            ->unique(fn (array $image): string => (string) Arr::get($image, 'src'))
            ->values()
            ->all();

        return $this->transformProductMediaCollection($images, true);
    }

    protected function resolvePriceSource(array $resolved): array
    {
        $providedPriceSource = $resolved['price_source'] ?? null;

        if (is_array($providedPriceSource)) {
            return $providedPriceSource;
        }

        $priceList = $resolved['price_list'] ?? null;

        return [
            'type' => is_array($priceList) ? 'price_list' : 'inventory',
            'unit_price' => (float) ($resolved['unit_price'] ?? 0),
            'purchase_price' => isset($resolved['purchase_price']) ? (float) $resolved['purchase_price'] : null,
            'price_includes_tax' => (bool) ($resolved['price_includes_tax'] ?? false),
            'price_list' => is_array($priceList) ? $priceList : null,
        ];
    }

    private function resolveCountryIso2Header(Request $request): ?string
    {
        $countryIso2 = $request->header('country-iso-2');

        return is_string($countryIso2) && filled($countryIso2)
            ? $countryIso2
            : null;
    }

    /**
     * @return array{taxable: float, tax: float, total: float}
     */
    private function resolveTaxBreakdown(float $amount, float $taxRate, bool $priceIncludesTax): array
    {
        if ($priceIncludesTax) {
            $breakdown = app(ExtractTaxFromGrossPrice::class)->handle($amount, $taxRate);

            return [
                'taxable' => $breakdown['taxable'],
                'tax' => $breakdown['tax'],
                'total' => $amount,
            ];
        }

        $tax = round($amount * ($taxRate / 100), 2);

        return [
            'taxable' => $amount,
            'tax' => $tax,
            'total' => round($amount + $tax, 2),
        ];
    }
}
