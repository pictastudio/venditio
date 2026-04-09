<?php

namespace PictaStudio\Venditio\Http\Resources\Traits;

use Illuminate\Database\Eloquent\Model;
use PictaStudio\Venditio\Http\Resources\{VenditioAnonymousResourceCollection, VenditioResourceResponse};

trait HasAttributesToExclude
{
    protected static function newCollection($resource): VenditioAnonymousResourceCollection
    {
        return new VenditioAnonymousResourceCollection($resource, static::class);
    }

    public function toResponse($request)
    {
        return (new VenditioResourceResponse($this))->toResponse($request);
    }

    protected function resolveResourceAttributes(): array
    {
        if ($this->resource === null) {
            return [];
        }

        if (is_array($this->resource)) {
            return $this->resource;
        }

        if (!$this->resource instanceof Model) {
            return $this->resource->toArray();
        }

        return $this->resource->attributesToArray();
    }

    protected function getAttributesToExclude(): array
    {
        $attributes = $this->exclude();

        if (!config('venditio.routes.api.include_timestamps')) {
            $attributes = array_merge($attributes, [
                // 'created_at',
                'updated_at',
                'deleted_at',
            ]);
        }

        return $attributes;
    }

    protected function exclude(): array
    {
        return [];
    }
}
