<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ShippingZone;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class StoreShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:100',
                Rule::unique($this->tableFor('shipping_zone'), 'code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0'],
            'country_ids' => ['sometimes', 'array'],
            'country_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('country'), 'id')],
            'region_ids' => ['sometimes', 'array'],
            'region_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('region'), 'id')],
            'province_ids' => ['sometimes', 'array'],
            'province_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('province'), 'id')],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
