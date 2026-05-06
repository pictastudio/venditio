<?php

namespace PictaStudio\Venditio\Actions\Products;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Models\Product;
use PictaStudio\Venditio\Support\ProductMedia;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateProduct
{
    public function handle(Product $product, array $payload): Product
    {
        $categoryIdsProvided = array_key_exists('category_ids', $payload);
        $categoryIds = Arr::pull($payload, 'category_ids', []);
        $collectionIdsProvided = array_key_exists('collection_ids', $payload);
        $collectionIds = Arr::pull($payload, 'collection_ids', []);
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $tagIds = Arr::pull($payload, 'tag_ids', []);
        $inventoryProvided = array_key_exists('inventory', $payload);
        $inventoryPayload = Arr::pull($payload, 'inventory');
        $imagesProvided = array_key_exists('images', $payload);
        $filesProvided = array_key_exists('files', $payload);
        $sharedMediaUpdates = [];

        if ($tagIdsProvided) {
            $this->validateTagProductTypeCompatibility(
                $tagIds ?? [],
                $payload['product_type_id'] ?? $product->product_type_id
            );
        }

        $product->fill(
            $this->prepareMediaPayload($product, $payload, $imagesProvided, $filesProvided, $sharedMediaUpdates)
        );
        $product->save();

        $this->propagateSharedVariantOptionMediaUpdates($product, $sharedMediaUpdates);

        if ($categoryIdsProvided) {
            $product->categories()->sync($categoryIds ?? []);
        }

        if ($collectionIdsProvided) {
            $product->collections()->sync($collectionIds ?? []);
        }

        if ($tagIdsProvided) {
            $product->tags()->sync($tagIds ?? []);
        }

        if ($inventoryProvided && is_array($inventoryPayload)) {
            $product->inventory()->updateOrCreate(
                ['product_id' => $product->getKey()],
                $inventoryPayload
            );
        }

        return $product->refresh()->load(['inventory', 'variantOptions']);
    }

    private function validateTagProductTypeCompatibility(array $tagIds, mixed $productTypeId): void
    {
        if ($tagIds === []) {
            return;
        }

        $resolvedProductTypeId = is_numeric($productTypeId) ? (int) $productTypeId : null;
        $invalidTags = resolve_model('tag')::withoutGlobalScopes()
            ->whereKey($tagIds)
            ->whereNotNull('product_type_id')
            ->when(
                $resolvedProductTypeId !== null,
                fn ($query) => $query->where('product_type_id', '!=', $resolvedProductTypeId),
                fn ($query) => $query
                    ->whereNotNull('product_type_id')
            )
            ->pluck('id')
            ->values()
            ->all();

        if ($invalidTags === []) {
            return;
        }

        throw ValidationException::withMessages([
            'tag_ids' => [
                'The selected tags are not compatible with the product type: ' . implode(', ', $invalidTags),
            ],
        ]);
    }

    private function prepareMediaPayload(
        Product $product,
        array $payload,
        bool $imagesProvided,
        bool $filesProvided,
        array &$sharedMediaUpdates
    ): array {
        $currentMedia = ProductMedia::normalizeProductMedia(
            $product->getAttribute('images'),
            $product->getAttribute('files')
        );
        $usedMediaIds = ProductMedia::collectUsedIds($currentMedia['images'], $currentMedia['files']);
        $validationErrors = [];

        if ($imagesProvided) {
            $validationErrors += $this->collectMediaItemPayloadErrors(
                Arr::get($payload, 'images'),
                'images',
                array_keys(collect($currentMedia['images'])->keyBy('id')->all()),
                true
            );
        }

        if ($filesProvided) {
            $validationErrors += $this->collectMediaItemPayloadErrors(
                Arr::get($payload, 'files'),
                'files',
                array_keys(collect($currentMedia['files'])->keyBy('id')->all()),
                false
            );
        }

        if ($validationErrors !== []) {
            throw ValidationException::withMessages($validationErrors);
        }

        if ($imagesProvided) {
            $payload['images'] = $this->mergeMediaCollection(
                $product,
                Arr::get($payload, 'images'),
                'images',
                $currentMedia['images'],
                true,
                $usedMediaIds,
                $sharedMediaUpdates
            );
        }

        if ($filesProvided) {
            $payload['files'] = $this->mergeMediaCollection(
                $product,
                Arr::get($payload, 'files'),
                'files',
                $currentMedia['files'],
                false,
                $usedMediaIds,
                $sharedMediaUpdates
            );
        }

        return $payload;
    }

    private function mergeMediaCollection(
        Product $product,
        mixed $items,
        string $folder,
        array $currentItems,
        bool $isImage,
        array &$usedMediaIds,
        array &$sharedMediaUpdates
    ): ?array {
        if ($items === null) {
            return null;
        }

        $items = is_array($items) ? $items : [];
        $currentItemsById = collect($currentItems)
            ->keyBy(fn (array $item) => (string) Arr::get($item, 'id'))
            ->all();

        foreach ($items as $index => $item) {
            $mediaId = Arr::get($item, 'id');

            if (is_string($mediaId) && $mediaId !== '') {
                /** @var array<string, mixed> $existingItem */
                $existingItem = $currentItemsById[$mediaId];
                $currentItemsById[$mediaId] = ProductMedia::mergeItem($existingItem, $item, $isImage);
                $this->trackSharedVariantOptionMediaUpdate(
                    $existingItem,
                    $currentItemsById[$mediaId],
                    $folder,
                    $isImage,
                    $sharedMediaUpdates
                );

                continue;
            }

            /** @var UploadedFile $file */
            $file = $item['file'];
            $generatedId = ProductMedia::generateUniqueId($usedMediaIds);
            $currentItemsById[$generatedId] = [
                'id' => $generatedId,
                'src' => $file->store("products/{$product->getKey()}/{$folder}", 'public'),
                'alt' => Arr::get($item, 'alt'),
                'name' => Arr::get($item, 'name'),
                'mimetype' => Arr::get($item, 'mimetype', $file->getMimeType()),
                'sort_order' => ProductMedia::resolveSortOrder(
                    Arr::get($item, 'sort_order'),
                    count($currentItemsById)
                ),
                'active' => ProductMedia::resolveBoolean(Arr::get($item, 'active'), true),
                'shared_from_variant_option' => false,
                ...($isImage ? [
                    'thumbnail' => ProductMedia::resolveBoolean(Arr::get($item, 'thumbnail'), false),
                ] : []),
            ];
        }

        return array_values($currentItemsById);
    }

    private function trackSharedVariantOptionMediaUpdate(
        array $existingItem,
        array $updatedItem,
        string $folder,
        bool $isImage,
        array &$sharedMediaUpdates
    ): void {
        if (!(bool) Arr::get($existingItem, 'shared_from_variant_option', false)) {
            return;
        }

        $src = Arr::get($existingItem, 'src');

        if (!is_string($src) || blank($src)) {
            return;
        }

        $metadata = Arr::only($updatedItem, [
            'name',
            'alt',
            'mimetype',
            'sort_order',
            'active',
            ...($isImage ? ['thumbnail'] : []),
        ]);

        $sharedMediaUpdates[] = [
            'folder' => $folder,
            'is_image' => $isImage,
            'src' => $src,
            'metadata' => $metadata,
        ];
    }

    private function propagateSharedVariantOptionMediaUpdates(Product $updatedProduct, array $updates): void
    {
        if ($updates === []) {
            return;
        }

        $productModelClass = resolve_model('product');

        $productModelClass::withoutGlobalScopes()
            ->whereKeyNot($updatedProduct->getKey())
            ->get(['id', 'images', 'files'])
            ->each(function (Product $product) use ($updates): void {
                $media = ProductMedia::normalizeProductMedia(
                    $product->getAttribute('images'),
                    $product->getAttribute('files')
                );
                $changed = false;

                foreach ($updates as $update) {
                    $folder = (string) Arr::get($update, 'folder');
                    $isImage = (bool) Arr::get($update, 'is_image');
                    $src = Arr::get($update, 'src');
                    $metadata = Arr::get($update, 'metadata', []);

                    if (!is_string($src) || !is_array($metadata) || !array_key_exists($folder, $media)) {
                        continue;
                    }

                    $media[$folder] = collect($media[$folder])
                        ->map(function (array $item) use ($src, $metadata, $isImage, &$changed): array {
                            if (
                                Arr::get($item, 'src') !== $src
                                || !(bool) Arr::get($item, 'shared_from_variant_option', false)
                            ) {
                                return $item;
                            }

                            $changed = true;

                            return ProductMedia::mergeItem($item, $metadata, $isImage);
                        })
                        ->values()
                        ->all();
                }

                if (!$changed) {
                    return;
                }

                $product->forceFill([
                    'images' => $media['images'],
                    'files' => $media['files'],
                ]);
                $product->save();
            });
    }

    /**
     * @param  array<int, string>  $existingIds
     * @return array<string, array<int, string>>
     */
    private function collectMediaItemPayloadErrors(
        mixed $items,
        string $attribute,
        array $existingIds,
        bool $isImage
    ): array {
        if ($items === null) {
            return [];
        }

        $items = is_array($items) ? $items : [];
        $errors = [];

        foreach ($items as $index => $item) {
            $errors += $this->validateMediaItemPayload(
                is_array($item) ? $item : [],
                $attribute,
                $index,
                $existingIds,
                $isImage
            );
        }

        return $errors;
    }

    /**
     * @param  array<int, string>  $existingIds
     * @return array<string, array<int, string>>
     */
    private function validateMediaItemPayload(
        array $item,
        string $attribute,
        int $index,
        array $existingIds,
        bool $isImage
    ): array {
        $hasId = is_string(Arr::get($item, 'id')) && Arr::get($item, 'id') !== '';
        $hasFile = Arr::get($item, 'file') instanceof UploadedFile;
        $errors = [];

        if (!$hasId && !$hasFile) {
            $errors["{$attribute}.{$index}.file"] = ['The file field is required when id is not present.'];
        }

        if ($hasId && !in_array((string) Arr::get($item, 'id'), $existingIds, true)) {
            $errors["{$attribute}.{$index}.id"] = ['The selected media item is invalid.'];
        }

        if ($hasId && $hasFile) {
            $errors["{$attribute}.{$index}.file"] = ['Omit the file when updating an existing media item.'];
        }

        if (!$isImage && array_key_exists('thumbnail', $item)) {
            $errors["{$attribute}.{$index}.thumbnail"] = ['The thumbnail field is only supported for images.'];
        }

        return $errors;
    }
}
