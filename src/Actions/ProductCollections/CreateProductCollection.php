<?php

namespace PictaStudio\Venditio\Actions\ProductCollections;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Models\ProductCollection;
use PictaStudio\Venditio\Support\CatalogImage;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreateProductCollection
{
    public function handle(array $payload): ProductCollection
    {
        $imagesProvided = array_key_exists('images', $payload);
        $images = Arr::pull($payload, 'images');

        if ($imagesProvided) {
            $errors = $this->collectImagePayloadErrors($images, []);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        /** @var ProductCollection $collection */
        $collection = resolve_model('product_collection')::create($payload);

        if ($imagesProvided) {
            $collection->images = $this->mergeImages($collection, [], $images);
            $collection->save();
        }

        return $collection->refresh();
    }

    private function mergeImages(ProductCollection $collection, array $currentImages, mixed $items): ?array
    {
        if ($items === null) {
            return null;
        }

        $usedIds = [];
        $imagesByType = collect(CatalogImage::normalizeCollection($currentImages, $usedIds))
            ->keyBy('type')
            ->all();

        foreach (is_array($items) ? $items : [] as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = CatalogImage::resolveType(Arr::get($item, 'type'));

            if ($type === null) {
                continue;
            }

            $existingImage = $imagesByType[$type] ?? null;
            $existingId = is_array($existingImage) ? Arr::get($existingImage, 'id') : null;

            if (isset($item['file']) && $item['file'] instanceof UploadedFile) {
                $imagesByType[$type] = [
                    'id' => is_string($existingId) && $existingId !== ''
                        ? $existingId
                        : CatalogImage::generateUniqueId($usedIds),
                    'type' => $type,
                    'src' => $item['file']->store("product_collections/{$collection->getKey()}/{$type}", 'public'),
                    'alt' => Arr::get($item, 'alt'),
                    'name' => Arr::get($item, 'name'),
                    'mimetype' => Arr::get($item, 'mimetype', $item['file']->getMimeType()),
                    'sort_order' => CatalogImage::resolveSortOrder(
                        Arr::get($item, 'sort_order'),
                        is_array($existingImage)
                            ? (int) Arr::get($existingImage, 'sort_order', CatalogImage::sortWeight($type))
                            : CatalogImage::sortWeight($type)
                    ),
                ];

                continue;
            }

            if (is_array($existingImage)) {
                $imagesByType[$type] = CatalogImage::mergeItem($existingImage, $item);
            }
        }

        return collect($imagesByType)
            ->sortBy(fn (array $image): int => CatalogImage::sortWeight(Arr::get($image, 'type')))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $existingIds
     * @return array<string, array<int, string>>
     */
    private function collectImagePayloadErrors(mixed $items, array $existingIds): array
    {
        if ($items === null) {
            return [];
        }

        $errors = [];

        foreach (is_array($items) ? $items : [] as $index => $item) {
            $item = is_array($item) ? $item : [];
            $hasFile = isset($item['file']) && $item['file'] instanceof UploadedFile;
            $imageId = Arr::get($item, 'id');
            $hasExistingId = is_string($imageId) && $imageId !== '' && in_array($imageId, $existingIds, true);

            if (!$hasFile && !$hasExistingId) {
                $errors["images.{$index}.file"] = ['The file field is required when the selected image does not exist yet.'];
            }

            if (is_string($imageId) && $imageId !== '' && !$hasExistingId) {
                $errors["images.{$index}.id"] = ['The selected image id is invalid.'];
            }
        }

        return $errors;
    }
}
