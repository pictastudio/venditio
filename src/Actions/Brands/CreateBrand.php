<?php

namespace PictaStudio\Venditio\Actions\Brands;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Models\Brand;
use PictaStudio\Venditio\Support\CatalogImage;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreateBrand
{
    public function handle(array $payload): Brand
    {
        $imagesProvided = array_key_exists('images', $payload);
        $tagIdsProvided = array_key_exists('tag_ids', $payload);
        $images = Arr::pull($payload, 'images');
        $tagIds = Arr::pull($payload, 'tag_ids', []);

        if ($imagesProvided) {
            $errors = $this->collectImagePayloadErrors($images, []);

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        }

        /** @var Brand $brand */
        $brand = resolve_model('brand')::create($payload);

        if ($imagesProvided) {
            $brand->images = $this->mergeImages($brand, [], $images);
            $brand->save();
        }

        if ($tagIdsProvided) {
            $brand->tags()->sync($tagIds ?? []);
        }

        return $brand->refresh()->load('tags');
    }

    private function mergeImages(Brand $brand, array $currentImages, mixed $items): ?array
    {
        if ($items === null) {
            return null;
        }

        $usedIds = CatalogImage::collectUsedIds($currentImages);
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
                    'src' => $item['file']->store("brands/{$brand->getKey()}/{$type}", 'public'),
                    'alt' => Arr::get($item, 'alt'),
                    'name' => Arr::get($item, 'name'),
                    'mimetype' => Arr::get($item, 'mimetype', $item['file']->getMimeType()),
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
