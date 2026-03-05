<?php

namespace PictaStudio\Venditio\Actions\Products;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Models\Product;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateProduct
{
    public function handle(Product $product, array $payload): Product
    {
        $categoryIdsProvided = array_key_exists('category_ids', $payload);
        $categoryIds = Arr::pull($payload, 'category_ids', []);
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $tagIds = Arr::pull($payload, 'tag_ids', []);
        $inventoryProvided = array_key_exists('inventory', $payload);
        $inventoryPayload = Arr::pull($payload, 'inventory');
        $imagesProvided = array_key_exists('images', $payload);
        $filesProvided = array_key_exists('files', $payload);

        if ($tagIdsProvided) {
            $this->validateTagProductTypeCompatibility(
                $tagIds ?? [],
                $payload['product_type_id'] ?? $product->product_type_id
            );
        }

        $product->fill(
            $this->prepareMediaPayload($product, $payload, $imagesProvided, $filesProvided)
        );
        $product->save();

        if ($categoryIdsProvided) {
            $product->categories()->sync($categoryIds ?? []);
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
        $invalidTags = resolve_model('product_tag')::withoutGlobalScopes()
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
        bool $filesProvided
    ): array {
        if ($imagesProvided) {
            $payload['images'] = $this->storeMediaCollection(
                $product,
                Arr::get($payload, 'images'),
                'images'
            );
        }

        if ($filesProvided) {
            $payload['files'] = $this->storeMediaCollection(
                $product,
                Arr::get($payload, 'files'),
                'files'
            );
        }

        return $payload;
    }

    private function storeMediaCollection(
        Product $product,
        mixed $items,
        string $folder
    ): ?array {
        if ($items === null) {
            return null;
        }

        return collect(is_array($items) ? $items : [])
            ->map(function (array $item) use ($product, $folder): array {
                /** @var UploadedFile $file */
                $file = $item['file'];

                return [
                    'src' => $file->store("products/{$product->getKey()}/{$folder}", 'public'),
                    'alt' => Arr::get($item, 'alt'),
                    'name' => Arr::get($item, 'name'),
                ];
            })
            ->values()
            ->all();
    }
}
