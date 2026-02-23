<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\ShippingRateTierValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ShippingRateTierValidation implements ShippingRateTierValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'shipping_rate_id' => ['required', 'integer', Rule::exists($this->tableFor('shipping_rate'), 'id')],
            'from_weight_kg' => ['required', 'numeric', 'min:0'],
            'to_weight_kg' => ['nullable', 'numeric', 'gt:from_weight_kg'],
            'additional_fee' => ['sometimes', 'numeric', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'shipping_rate_id' => ['sometimes', 'integer', Rule::exists($this->tableFor('shipping_rate'), 'id')],
            'from_weight_kg' => ['sometimes', 'numeric', 'min:0'],
            'to_weight_kg' => ['nullable', 'numeric', 'gt:from_weight_kg'],
            'additional_fee' => ['sometimes', 'numeric', 'min:0'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
