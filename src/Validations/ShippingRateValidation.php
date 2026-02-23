<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\ShippingRateValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ShippingRateValidation implements ShippingRateValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'shipping_carrier_id' => ['required', 'integer', Rule::exists($this->tableFor('shipping_carrier'), 'id')],
            'shipping_zone_id' => ['required', 'integer', Rule::exists($this->tableFor('shipping_zone'), 'id')],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'base_fee' => ['sometimes', 'numeric', 'min:0'],
            'min_order_subtotal' => ['nullable', 'numeric', 'min:0'],
            'max_order_subtotal' => ['nullable', 'numeric', 'gte:min_order_subtotal'],
            'estimated_delivery_min_days' => ['nullable', 'integer', 'min:0'],
            'estimated_delivery_max_days' => ['nullable', 'integer', 'gte:estimated_delivery_min_days'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'shipping_carrier_id' => ['sometimes', 'integer', Rule::exists($this->tableFor('shipping_carrier'), 'id')],
            'shipping_zone_id' => ['sometimes', 'integer', Rule::exists($this->tableFor('shipping_zone'), 'id')],
            'name' => ['sometimes', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'base_fee' => ['sometimes', 'numeric', 'min:0'],
            'min_order_subtotal' => ['nullable', 'numeric', 'min:0'],
            'max_order_subtotal' => ['nullable', 'numeric', 'gte:min_order_subtotal'],
            'estimated_delivery_min_days' => ['nullable', 'integer', 'min:0'],
            'estimated_delivery_max_days' => ['nullable', 'integer', 'gte:estimated_delivery_min_days'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
