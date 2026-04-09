<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};
use PictaStudio\Venditio\Http\Resources\Traits\ResolvesModelResource;

class DiscountResource extends JsonResource
{
    use CanTransformAttributes;
    use HasAttributesToExclude;
    use ResolvesModelResource;

    public function toArray(Request $request)
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
            'discountable' => $this->whenLoaded(
                'discountable',
                fn () => filled($this->resource->discountable)
                    ? $this->resolveResourceForModel($this->resource->discountable)
                    : null
            ),
            'applications' => DiscountApplicationResource::collection($this->whenLoaded('applications')),
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            //
        ];
    }
}
