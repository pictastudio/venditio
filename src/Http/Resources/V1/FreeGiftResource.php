<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class FreeGiftResource extends JsonResource
{
    use CanTransformAttributes;
    use HasAttributesToExclude;

    public function toArray(Request $request)
    {
        return $this->applyAttributesTransformation(
            collect($this->resolveResourceAttributes())
                ->except($this->getAttributesToExclude())
                ->map(fn (mixed $value, string $key) => $this->mutateAttributeBasedOnCast($key, $value))
                ->merge([
                    'qualifying_user_ids' => $this->relationIds('qualifyingUsers'),
                    'qualifying_product_ids' => $this->relationIds('qualifyingProducts'),
                    'gift_product_ids' => $this->relationIds('giftProducts'),
                    'qualifying_users' => UserResource::collection($this->whenLoaded('qualifyingUsers')),
                    'qualifying_products' => ProductResource::collection($this->whenLoaded('qualifyingProducts')),
                    'gift_products' => ProductResource::collection($this->whenLoaded('giftProducts')),
                ])
                ->toArray()
        );
    }

    protected function transformAttributes(): array
    {
        return [
            //
        ];
    }

    protected function relationIds(string $relation): array
    {
        if (!$this->resource->relationLoaded($relation)) {
            return [];
        }

        return collect($this->resource->getRelation($relation))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
