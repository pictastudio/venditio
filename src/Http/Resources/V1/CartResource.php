<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class CartResource extends JsonResource
{
    use CanTransformAttributes;
    use HasAttributesToExclude;

    public function toArray(Request $request): array|Arrayable|JsonSerializable
    {
        return $this->applyAttributesTransformation(
            collect($this->resolveResourceAttributes())
                ->except($this->getAttributesToExclude())
                ->map(fn (mixed $value, string $key) => (
                    $this->mutateAttributeBasedOnCast($key, $value)
                ))
                ->merge($this->getRelationshipsToInclude())
                ->toArray()
        );
    }

    protected function getRelationshipsToInclude(): array
    {
        return [
            'user' => UserResource::make($this->whenLoaded('user')),
            'order' => OrderResource::make($this->whenLoaded('order')),
            'lines' => CartLineResource::collection($this->whenLoaded('lines')),
            'discounts' => DiscountResource::collection($this->whenLoaded('discounts')),
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            //
        ];
    }
}
