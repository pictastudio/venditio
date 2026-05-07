<?php

namespace PictaStudio\Venditio\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\{Arr, Str};
use Illuminate\Validation\ValidationException;

class CatalogImage
{
    public const TYPES = ['thumb', 'cover'];

    public static function normalizeCollection(mixed $items, array &$usedIds = []): array
    {
        if (is_string($items)) {
            $items = json_decode($items, true) ?: [];
        }

        if (!is_array($items)) {
            return [];
        }

        $seenTypes = [];

        return collect($items)
            ->filter(fn (mixed $item) => is_array($item))
            ->values()
            ->map(function (array $item, int $index) use (&$usedIds): ?array {
                $type = self::resolveType(Arr::get($item, 'type'));

                if (blank(Arr::get($item, 'src')) && !(Arr::get($item, 'file') instanceof UploadedFile)) {
                    return null;
                }

                return [
                    'id' => self::resolveUniqueId(Arr::get($item, 'id'), $usedIds),
                    'type' => $type,
                    'name' => Arr::get($item, 'name'),
                    'alt' => Arr::get($item, 'alt'),
                    'mimetype' => Arr::get($item, 'mimetype'),
                    'src' => Arr::get($item, 'src'),
                    'sort_order' => self::resolveSortOrder(
                        Arr::get($item, 'sort_order'),
                        self::defaultSortOrder($type, $index)
                    ),
                    '_type_weight' => self::sortWeight($type),
                ];
            })
            ->filter()
            ->sortBy([
                ['sort_order', 'asc'],
                ['_type_weight', 'asc'],
                ['id', 'asc'],
            ])
            ->filter(function (array $item) use (&$seenTypes): bool {
                $type = Arr::get($item, 'type');

                if ($type === null) {
                    return true;
                }

                if (array_key_exists($type, $seenTypes)) {
                    return false;
                }

                $seenTypes[$type] = true;

                return true;
            })
            ->map(fn (array $item): array => Arr::except($item, '_type_weight'))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $existingItem
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mergeItem(array $existingItem, array $payload): array
    {
        $updated = $existingItem;

        foreach (['name', 'alt', 'mimetype', 'src'] as $attribute) {
            if (array_key_exists($attribute, $payload)) {
                $updated[$attribute] = Arr::get($payload, $attribute);
            }
        }

        if (array_key_exists('sort_order', $payload)) {
            $updated['sort_order'] = self::resolveSortOrder(
                Arr::get($payload, 'sort_order'),
                (int) Arr::get($existingItem, 'sort_order', self::sortWeight(Arr::get($existingItem, 'type')))
            );
        }

        if (array_key_exists('type', $payload)) {
            $updated['type'] = self::resolveType(Arr::get($payload, 'type'));
        }

        return $updated;
    }

    public static function mergeCollection(Model $model, array $currentImages, mixed $items, string $folder): ?array
    {
        if ($items === null) {
            return null;
        }

        $usedIds = [];
        $images = self::normalizeCollection($currentImages, $usedIds);

        if (!is_array($items) || $items === []) {
            return $images;
        }

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = self::resolveType(Arr::get($item, 'type'));
            $imageId = self::scalarString(Arr::get($item, 'id'));
            $existingIndex = self::findExistingImageIndex($images, $imageId, $type);
            $existingImage = $existingIndex === null ? null : $images[$existingIndex];

            if (Arr::get($item, 'file') instanceof UploadedFile) {
                /** @var UploadedFile $file */
                $file = Arr::get($item, 'file');
                $image = [
                    'id' => is_array($existingImage) && filled(Arr::get($existingImage, 'id'))
                        ? (string) Arr::get($existingImage, 'id')
                        : self::generateUniqueId($usedIds),
                    'type' => $type,
                    'src' => $file->store(self::storagePath($folder, $model, $type), 'public'),
                    'alt' => Arr::get($item, 'alt'),
                    'name' => Arr::get($item, 'name'),
                    'mimetype' => Arr::get($item, 'mimetype', $file->getMimeType()),
                    'sort_order' => self::resolveSortOrder(
                        Arr::get($item, 'sort_order'),
                        is_array($existingImage)
                            ? (int) Arr::get($existingImage, 'sort_order', self::defaultSortOrder($type, count($images)))
                            : self::defaultSortOrder($type, count($images))
                    ),
                ];

                if ($existingIndex === null) {
                    $images[] = $image;

                    continue;
                }

                $images[$existingIndex] = $image;

                continue;
            }

            if ($existingIndex !== null && is_array($existingImage)) {
                $images[$existingIndex] = self::mergeItem($existingImage, $item);
            }
        }

        $normalizedUsedIds = [];

        return self::normalizeCollection($images, $normalizedUsedIds);
    }

    public static function validatePayload(
        mixed $items,
        array $existingIds,
        string $attribute = 'images',
        array $existingImages = []
    ): void {
        $errors = self::collectPayloadErrors($items, $existingIds, $attribute);
        $errors = array_replace_recursive(
            $errors,
            self::collectFinalTypeErrors($items, $existingImages, $attribute)
        );

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, string>  $existingIds
     * @return array<string, array<int, string>>
     */
    public static function collectPayloadErrors(mixed $items, array $existingIds, string $attribute = 'images'): array
    {
        if ($items === null) {
            return [];
        }

        $errors = [];
        $seenTypes = [];

        foreach (is_array($items) ? $items : [] as $index => $item) {
            $item = is_array($item) ? $item : [];
            $type = self::resolveType(Arr::get($item, 'type'));

            if ($type !== null) {
                if (array_key_exists($type, $seenTypes)) {
                    $errors["{$attribute}.{$index}.type"] = ['The type field must be unique when it is not null.'];
                }

                $seenTypes[$type] = true;
            }

            $hasFile = Arr::get($item, 'file') instanceof UploadedFile;
            $imageId = self::scalarString(Arr::get($item, 'id'));
            $hasExistingId = filled($imageId) && in_array($imageId, $existingIds, true);

            if (!$hasFile && !$hasExistingId) {
                $errors["{$attribute}.{$index}.file"] = ['The file field is required when the selected image does not exist yet.'];
            }

            if (filled($imageId) && !$hasExistingId) {
                $errors["{$attribute}.{$index}.id"] = ['The selected image id is invalid.'];
            }
        }

        return $errors;
    }

    /**
     * @param  array<int, array{id?: mixed}>  ...$collections
     * @return array<int, string>
     */
    public static function collectUsedIds(array ...$collections): array
    {
        return collect($collections)
            ->flatten(1)
            ->map(fn (mixed $item) => is_array($item) ? Arr::get($item, 'id') : null)
            ->filter(fn (mixed $id) => is_scalar($id) && filled((string) $id))
            ->map(fn (mixed $id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function generateUniqueId(array &$usedIds = []): string
    {
        do {
            $id = (string) Str::ulid();
        } while (in_array($id, $usedIds, true));

        $usedIds[] = $id;

        return $id;
    }

    public static function resolveType(mixed $type): ?string
    {
        if (!is_string($type) || blank($type)) {
            return null;
        }

        $normalized = mb_strtolower(mb_trim($type));

        return in_array($normalized, self::TYPES, true) ? $normalized : null;
    }

    public static function sortWeight(mixed $type): int
    {
        $index = array_search(self::resolveType($type), self::TYPES, true);

        return is_int($index) ? $index : count(self::TYPES);
    }

    public static function defaultSortOrder(mixed $type, int $index = 0): int
    {
        $resolvedType = self::resolveType($type);

        return $resolvedType === null
            ? count(self::TYPES) + $index
            : self::sortWeight($resolvedType);
    }

    public static function resolveSortOrder(mixed $value, int $default): int
    {
        if (is_numeric($value) && (int) $value >= 0) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * @param  array<int, array{id?: mixed, type?: mixed}>  $existingImages
     * @return array<string, array<int, string>>
     */
    private static function collectFinalTypeErrors(mixed $items, array $existingImages, string $attribute): array
    {
        if ($items === null || $existingImages === []) {
            return [];
        }

        $existingTypes = collect(self::normalizeCollection($existingImages))
            ->filter(fn (array $image): bool => self::resolveType(Arr::get($image, 'type')) !== null)
            ->mapWithKeys(fn (array $image): array => [
                self::resolveType(Arr::get($image, 'type')) => self::scalarString(Arr::get($image, 'id')),
            ])
            ->all();

        if ($existingTypes === []) {
            return [];
        }

        $errors = [];
        $releasedTypes = self::collectReleasedTypes($items, $existingTypes);

        foreach (is_array($items) ? $items : [] as $index => $item) {
            $item = is_array($item) ? $item : [];
            $type = self::resolveType(Arr::get($item, 'type'));
            $imageId = self::scalarString(Arr::get($item, 'id'));
            $existingIdForType = $type === null ? null : ($existingTypes[$type] ?? null);

            if (
                $type !== null
                && filled($imageId)
                && filled($existingIdForType)
                && $existingIdForType !== $imageId
                && !($releasedTypes[$type] ?? false)
            ) {
                $errors["{$attribute}.{$index}.type"] = ['The selected image type is already in use.'];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, string|null>  $existingTypes
     * @return array<string, bool>
     */
    private static function collectReleasedTypes(mixed $items, array $existingTypes): array
    {
        $releasedTypes = [];

        foreach (is_array($items) ? $items : [] as $item) {
            $item = is_array($item) ? $item : [];
            $imageId = self::scalarString(Arr::get($item, 'id'));

            if (!filled($imageId) || !array_key_exists('type', $item)) {
                continue;
            }

            foreach ($existingTypes as $type => $existingId) {
                if ($existingId === $imageId && self::resolveType(Arr::get($item, 'type')) !== $type) {
                    $releasedTypes[$type] = true;
                }
            }
        }

        return $releasedTypes;
    }

    private static function resolveUniqueId(mixed $id, array &$usedIds): string
    {
        if (is_scalar($id)) {
            $candidate = (string) $id;

            if (filled($candidate) && !in_array($candidate, $usedIds, true)) {
                $usedIds[] = $candidate;

                return $candidate;
            }
        }

        return self::generateUniqueId($usedIds);
    }

    private static function findExistingImageIndex(array $images, ?string $id, ?string $type): ?int
    {
        if (filled($id)) {
            foreach ($images as $index => $image) {
                if ((string) Arr::get($image, 'id') === $id) {
                    return $index;
                }
            }
        }

        if ($type === null) {
            return null;
        }

        foreach ($images as $index => $image) {
            if (self::resolveType(Arr::get($image, 'type')) === $type) {
                return $index;
            }
        }

        return null;
    }

    private static function storagePath(string $folder, Model $model, ?string $type): string
    {
        return implode('/', array_filter([
            $folder,
            (string) $model->getKey(),
            $type ?? 'images',
            now()->format('Y/m/d'),
        ]));
    }

    private static function scalarString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
