<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ShippingMethod;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateShippingMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shippingMethod = $this->route('shipping_method');

        return [
            'code' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique($this->tableFor('shipping_method'), 'code')->ignore($shippingMethod?->getKey()),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'flat_fee' => ['sometimes', 'numeric', 'min:0'],
            'volumetric_divisor' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
