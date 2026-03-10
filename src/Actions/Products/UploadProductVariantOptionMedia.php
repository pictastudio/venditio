<?php

namespace PictaStudio\Venditio\Actions\Products;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Models\{Product, ProductVariantOption};
use PictaStudio\Venditio\Support\ProductMedia;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UploadProductVariantOptionMedia
{
    public function handle(Product $product, ProductVariantOption $productVariantOption, array $payload): Collection
    {
        $matchingProducts = $this->resolveMatchingProducts($product, $productVariantOption);

        if ($matchingProducts->isEmpty()) {
            throw ValidationException::withMessages([
                'product_variant_option_id' => ['The selected variant option does not match the target product.'],
            ]);
        }

        $storedMedia = [
            'images' => $this->storeSharedMediaCollection(
                Arr::get($payload, 'images'),
                $product,
                $productVariantOption,
                'images',
                true
            ),
            'files' => $this->storeSharedMediaCollection(
                Arr::get($payload, 'files'),
                $product,
                $productVariantOption,
                'files',
                false
            ),
        ];

        if ($storedMedia['images'] === [] && $storedMedia['files'] === []) {
            throw ValidationException::withMessages([
                'images' => ['At least one image or file upload is required.'],
                'files' => ['At least one image or file upload is required.'],
            ]);
        }

        $matchingProducts->each(function (Product $matchingProduct) use ($storedMedia): void {
            $normalizedMedia = ProductMedia::normalizeProductMedia(
                $matchingProduct->getAttribute('images'),
                $matchingProduct->getAttribute('files')
            );
            $usedIds = ProductMedia::collectUsedIds($normalizedMedia['images'], $normalizedMedia['files']);

            $matchingProduct->forceFill([
                'images' => [
                    ...$normalizedMedia['images'],
                    ...$this->cloneMediaItems($storedMedia['images'], $normalizedMedia['images'], true, $usedIds),
                ],
                'files' => [
                    ...$normalizedMedia['files'],
                    ...$this->cloneMediaItems($storedMedia['files'], $normalizedMedia['files'], false, $usedIds),
                ],
            ]);
            $matchingProduct->save();
        });

        return $matchingProducts
            ->map(fn (Product $matchingProduct) => $matchingProduct->refresh()->load(['inventory', 'variantOptions.productVariant']));
    }

    private function resolveMatchingProducts(Product $product, ProductVariantOption $productVariantOption): Collection
    {
        $productModelClass = resolve_model('product');

        $query = $productModelClass::withoutGlobalScopes()
            ->whereHas('variantOptions', fn ($builder) => $builder->whereKey($productVariantOption->getKey()));

        if ($product->parent_id) {
            return $query
                ->whereKey($product->getKey())
                ->get();
        }

        return $query
            ->where('parent_id', $product->getKey())
            ->get();
    }

    private function storeSharedMediaCollection(
        mixed $items,
        Product $product,
        ProductVariantOption $productVariantOption,
        string $folder,
        bool $isImage
    ): array {
        $items = is_array($items) ? $items : [];

        return collect($items)
            ->map(function (array $item, int $index) use ($product, $productVariantOption, $folder, $isImage): array {
                /** @var UploadedFile $file */
                $file = $item['file'];

                return [
                    'src' => $file->store(
                        "products/{$product->getKey()}/variant_options/{$productVariantOption->getKey()}/{$folder}",
                        'public'
                    ),
                    'alt' => Arr::get($item, 'alt'),
                    'name' => Arr::get($item, 'name'),
                    'mimetype' => Arr::get($item, 'mimetype', $file->getMimeType()),
                    'sort_order' => $index,
                    'active' => ProductMedia::resolveBoolean(Arr::get($item, 'active'), true),
                    'shared_from_variant_option' => true,
                    ...($isImage ? [
                        'thumbnail' => ProductMedia::resolveBoolean(Arr::get($item, 'thumbnail'), false),
                    ] : []),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<int, array<string, mixed>>  $currentItems
     * @param  array<int, string>  $usedIds
     * @return array<int, array<string, mixed>>
     */
    private function cloneMediaItems(array $items, array $currentItems, bool $isImage, array &$usedIds): array
    {
        if ($items === []) {
            return [];
        }

        $sharedSortOffset = collect($currentItems)
            ->filter(fn (array $item): bool => (bool) Arr::get($item, 'shared_from_variant_option', false))
            ->max('sort_order');

        $nextSortOrder = is_numeric($sharedSortOffset) ? ((int) $sharedSortOffset + 1) : 0;

        return collect($items)
            ->map(function (array $item) use (&$usedIds, &$nextSortOrder, $isImage): array {
                return [
                    'id' => ProductMedia::generateUniqueId($usedIds),
                    'src' => Arr::get($item, 'src'),
                    'alt' => Arr::get($item, 'alt'),
                    'name' => Arr::get($item, 'name'),
                    'mimetype' => Arr::get($item, 'mimetype'),
                    'sort_order' => $nextSortOrder++,
                    'active' => ProductMedia::resolveBoolean(Arr::get($item, 'active'), true),
                    'shared_from_variant_option' => true,
                    ...($isImage ? [
                        'thumbnail' => ProductMedia::resolveBoolean(Arr::get($item, 'thumbnail'), false),
                    ] : []),
                ];
            })
            ->values()
            ->all();
    }
}
