<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Currency;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class StoreCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country_ids' => ['sometimes', 'array'],
            'country_ids.*' => ['integer', Rule::exists($this->tableFor('country'), 'id')],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'size:3', Rule::unique($this->tableFor('currency'), 'code')],
            'symbol' => ['nullable', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
            'decimal_places' => ['sometimes', 'integer', 'min:0', 'max:9'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
