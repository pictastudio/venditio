<?php

namespace PictaStudio\Venditio\Http\Resources\Traits;

use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use JsonSerializable;
use PictaStudio\Venditio\Support\ProductMedia;
use Stringable;
use UnitEnum;

trait CanTransformAttributes
{
    public function applyAttributesTransformation(array $attributes): array
    {
        $transformedAttributes = $this->transformAttributes();

        if (empty($transformedAttributes)) {
            return $attributes;
        }

        foreach ($transformedAttributes as $key => $closure) {
            Arr::set($attributes, $key, $closure(Arr::get($attributes, $key)));
        }

        return $attributes;
    }

    protected function transformAttributes(): array
    {
        return [];
    }

    protected function transformProductMediaCollection(mixed $items, bool $isImage): array
    {
        $normalized = ProductMedia::normalizeCollection($items, isImage: $isImage);
        $activeFilter = $this->resolveProductMediaActiveFilter();

        if ($activeFilter !== null) {
            $normalized = array_values(
                array_filter(
                    $normalized,
                    fn (array $item): bool => (bool) Arr::get($item, 'active', true) === $activeFilter
                )
            );
        }

        return collect($normalized)
            ->map(function (array $item) use ($isImage): array {
                $transformed = [
                    'id' => Arr::get($item, 'id'),
                    'name' => Arr::get($item, 'name'),
                    'alt' => Arr::get($item, 'alt'),
                    'mimetype' => Arr::get($item, 'mimetype'),
                    'sort_order' => Arr::get($item, 'sort_order'),
                    'active' => (bool) Arr::get($item, 'active', true),
                    'shared_from_variant_option' => (bool) Arr::get($item, 'shared_from_variant_option', false),
                    'src' => $this->getImageAssetUrl(Arr::get($item, 'src')),
                ];

                if ($isImage) {
                    $transformed['thumbnail'] = (bool) Arr::get($item, 'thumbnail', false);
                }

                return $transformed;
            })
            ->values()
            ->all();
    }

    private function mutateAttributeBasedOnCast(string $key, mixed $value): mixed
    {
        $model = $this->resource;

        if (!$model->hasCast($key)) {
            return $this->normalizeCustomObjectAttribute($value);
        }

        $cast = $model->getCasts()[$key];

        if (str_contains($cast, 'decimal')) {
            return (float) $value;
        }

        if (in_array($cast, ['int', 'integer'])) {
            return (int) $value;
        }

        if (in_array($cast, ['bool', 'boolean'])) {
            return (bool) $value;
        }

        return $this->normalizeCustomObjectAttribute($value);
    }

    private function getImageAssetUrl(?string $image): ?string
    {
        if (blank($image)) {
            return null;
        }

        return URL::isValidUrl($image) ? $image : asset('storage/' . $image);
    }

    private function resolveProductMediaActiveFilter(): ?bool
    {
        $request = request();

        if ($request->query->has('active')) {
            return filter_var($request->query('active'), FILTER_VALIDATE_BOOL);
        }

        if ($request->query->has('is_active')) {
            return filter_var($request->query('is_active'), FILTER_VALIDATE_BOOL);
        }

        if (
            filter_var($request->query('exclude_all_scopes', false), FILTER_VALIDATE_BOOL)
            || filter_var($request->query('exclude_active_scope', false), FILTER_VALIDATE_BOOL)
        ) {
            return null;
        }

        return true;
    }

    private function normalizeCustomObjectAttribute(mixed $value): mixed
    {
        if (!is_object($value)) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof JsonSerializable) {
            return $value->jsonSerialize();
        }

        if (method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        if ($value instanceof Stringable || method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $value;
    }
}
