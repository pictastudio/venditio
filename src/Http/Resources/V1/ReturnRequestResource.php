<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class ReturnRequestResource extends JsonResource
{
    use CanTransformAttributes;
    use HasAttributesToExclude;

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
            'order' => OrderResource::make($this->whenLoaded('order')),
            'user' => UserResource::make($this->whenLoaded('user')),
            'return_reason' => ReturnReasonResource::make($this->whenLoaded('returnReason')),
            'lines' => ReturnRequestLineResource::collection($this->whenLoaded('lines')),
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            //
        ];
    }
}
