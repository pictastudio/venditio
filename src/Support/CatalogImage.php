<?php

namespace PictaStudio\Venditio\Support;

use Illuminate\Support\{Arr, Str};

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

        return collect($items)
            ->filter(fn (mixed $item) => is_array($item))
            ->map(function (array $item) use (&$usedIds): ?array {
                $type = self::resolveType(Arr::get($item, 'type'));

                if ($type === null) {
                    return null;
                }

                return [
                    'id' => self::resolveUniqueId(Arr::get($item, 'id'), $usedIds),
                    'type' => $type,
                    'name' => Arr::get($item, 'name'),
                    'alt' => Arr::get($item, 'alt'),
                    'mimetype' => Arr::get($item, 'mimetype'),
                    'src' => Arr::get($item, 'src'),
                ];
            })
            ->filter()
            ->sortBy(fn (array $item): int => self::sortWeight(Arr::get($item, 'type')))
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

        foreach (['name', 'alt', 'mimetype'] as $attribute) {
            if (array_key_exists($attribute, $payload)) {
                $updated[$attribute] = Arr::get($payload, $attribute);
            }
        }

        if (array_key_exists('type', $payload)) {
            $updated['type'] = self::resolveType(Arr::get($payload, 'type')) ?? Arr::get($existingItem, 'type');
        }

        return $updated;
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
        if (!is_string($type)) {
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
