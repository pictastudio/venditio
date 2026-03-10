<?php

namespace PictaStudio\Venditio\Support;

use Illuminate\Support\{Arr, Str};

class ProductMedia
{
    public static function normalizeProductMedia(mixed $images, mixed $files): array
    {
        $usedIds = [];

        return [
            'images' => self::normalizeCollection($images, $usedIds, true),
            'files' => self::normalizeCollection($files, $usedIds, false),
        ];
    }

    public static function normalizeCollection(mixed $items, array &$usedIds = [], bool $isImage = false): array
    {
        if (is_string($items)) {
            $items = json_decode($items, true) ?: [];
        }

        if (!is_array($items)) {
            return [];
        }

        return collect($items)
            ->filter(fn (mixed $item) => is_array($item))
            ->map(function (array $item, int $index) use (&$usedIds, $isImage): array {
                $normalized = [
                    'id' => self::resolveUniqueId(Arr::get($item, 'id'), $usedIds),
                    'name' => Arr::get($item, 'name'),
                    'alt' => Arr::get($item, 'alt'),
                    'mimetype' => Arr::get($item, 'mimetype'),
                    'src' => Arr::get($item, 'src'),
                    'sort_order' => self::resolveSortOrder(Arr::get($item, 'sort_order'), $index),
                    'active' => self::resolveBoolean(Arr::get($item, 'active'), true),
                    'shared_from_variant_option' => self::resolveBoolean(
                        Arr::get($item, 'shared_from_variant_option'),
                        false
                    ),
                ];

                if ($isImage) {
                    $normalized['thumbnail'] = self::resolveBoolean(Arr::get($item, 'thumbnail'), false);
                }

                return $normalized;
            })
            ->sortBy([
                ['shared_from_variant_option', 'asc'],
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->all();
    }

    public static function resolveBoolean(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function resolveSortOrder(mixed $value, int $default): int
    {
        if (is_numeric($value) && (int) $value >= 0) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * @param  array<string, mixed>  $existingItem
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function mergeItem(array $existingItem, array $payload, bool $isImage): array
    {
        $updated = $existingItem;

        foreach (['name', 'alt', 'mimetype'] as $attribute) {
            if (array_key_exists($attribute, $payload)) {
                $updated[$attribute] = Arr::get($payload, $attribute);
            }
        }

        if (array_key_exists('sort_order', $payload)) {
            $updated['sort_order'] = self::resolveSortOrder(
                Arr::get($payload, 'sort_order'),
                (int) Arr::get($existingItem, 'sort_order', 0)
            );
        }

        if (array_key_exists('active', $payload)) {
            $updated['active'] = self::resolveBoolean(
                Arr::get($payload, 'active'),
                (bool) Arr::get($existingItem, 'active', true)
            );
        }

        if ($isImage && array_key_exists('thumbnail', $payload)) {
            $updated['thumbnail'] = self::resolveBoolean(
                Arr::get($payload, 'thumbnail'),
                (bool) Arr::get($existingItem, 'thumbnail', false)
            );
        }

        if (array_key_exists('shared_from_variant_option', $payload)) {
            $updated['shared_from_variant_option'] = self::resolveBoolean(
                Arr::get($payload, 'shared_from_variant_option'),
                (bool) Arr::get($existingItem, 'shared_from_variant_option', false)
            );
        }

        return $updated;
    }

    public static function generateUniqueId(array &$usedIds = []): string
    {
        do {
            $id = (string) Str::ulid();
        } while (in_array($id, $usedIds, true));

        $usedIds[] = $id;

        return $id;
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
}
