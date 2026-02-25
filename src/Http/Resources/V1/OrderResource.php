<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class OrderResource extends JsonResource
{
    use CanTransformAttributes;
    use HasAttributesToExclude;

    public function toArray(Request $request)
    {
        return $this->applyAttributesTransformation(
            collect(parent::toArray($request))
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
            'shipping_status' => ShippingStatusResource::make($this->whenLoaded('shippingStatus')),
            'lines' => OrderLineResource::collection($this->whenLoaded('lines')),
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
