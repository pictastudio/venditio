<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\ShippingCarrierValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ShippingCarrierValidation implements ShippingCarrierValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique($this->tableFor('shipping_carrier'), 'code')],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'volumetric_divisor' => ['sometimes', 'numeric', 'gt:0'],
            'weight_rounding_step_kg' => ['sometimes', 'numeric', 'gt:0'],
            'weight_rounding_mode' => ['sometimes', 'string', Rule::in(['ceil', 'floor', 'round'])],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        $carrierId = request()->route('shipping_carrier')?->getKey();

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique($this->tableFor('shipping_carrier'), 'code')->ignore($carrierId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'volumetric_divisor' => ['sometimes', 'numeric', 'gt:0'],
            'weight_rounding_step_kg' => ['sometimes', 'numeric', 'gt:0'],
            'weight_rounding_mode' => ['sometimes', 'string', Rule::in(['ceil', 'floor', 'round'])],
            'metadata' => ['nullable', 'array'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
