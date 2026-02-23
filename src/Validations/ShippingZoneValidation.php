<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\ShippingZoneValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ShippingZoneValidation implements ShippingZoneValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique($this->tableFor('shipping_zone'), 'code')],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer'],
            'is_fallback' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        $zoneId = request()->route('shipping_zone')?->getKey();

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique($this->tableFor('shipping_zone'), 'code')->ignore($zoneId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer'],
            'is_fallback' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
