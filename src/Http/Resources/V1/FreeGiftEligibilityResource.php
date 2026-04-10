<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FreeGiftEligibilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(),
            'name' => $this->resource->name,
            'mode' => $this->enumValue($this->resource->mode),
            'selection_mode' => $this->enumValue($this->resource->selection_mode),
            'allow_decline' => (bool) $this->resource->allow_decline,
            'product_match_mode' => $this->enumValue($this->resource->product_match_mode),
            'products' => ProductResource::collection($this->resource->giftProducts ?? collect()),
            'selected_product_ids' => $this->normalizeIds($this->resource->getAttribute('selected_product_ids')),
            'declined_product_ids' => $this->normalizeIds($this->resource->getAttribute('declined_product_ids')),
            'in_cart_product_ids' => $this->normalizeIds($this->resource->getAttribute('in_cart_product_ids')),
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        return is_object($value) && isset($value->value)
            ? $value->value
            : $value;
    }

    private function normalizeIds(mixed $ids): array
    {
        return collect(is_array($ids) ? $ids : [])
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
