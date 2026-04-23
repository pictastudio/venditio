<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use JsonSerializable;
use PictaStudio\Venditio\FreeGifts\FreeGiftEligibilityResolver;
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
            'shipping_method' => ShippingMethodResource::make($this->whenLoaded('shippingMethod')),
            'shipping_zone' => ShippingZoneResource::make($this->whenLoaded('shippingZone')),
            'lines' => CartLineResource::collection($this->whenLoaded('lines')),
            'discounts' => DiscountResource::collection($this->whenLoaded('discounts')),
            'valid_discounts' => DiscountResource::collection($this->whenLoaded('validDiscounts')),
            'expired_discounts' => DiscountResource::collection($this->whenLoaded('expiredDiscounts')),
            'free_gifts' => FreeGiftEligibilityResource::collection($this->resolveEligibleFreeGifts()),
        ];
    }

    protected function transformAttributes(): array
    {
        return [
            //
        ];
    }

    protected function resolveEligibleFreeGifts()
    {
        if (!$this->resource instanceof Model) {
            return collect();
        }

        if ($this->resource->relationLoaded('eligibleFreeGifts')) {
            return $this->resource->getRelation('eligibleFreeGifts');
        }

        return app(FreeGiftEligibilityResolver::class)->resolveForCart($this->resource);
    }
}
