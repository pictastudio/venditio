<?php

namespace PictaStudio\Venditio\Http\Resources\Traits;

use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;
use JsonSerializable;
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
