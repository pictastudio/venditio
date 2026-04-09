<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Currency;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateCurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currencyId = $this->route('currency')?->getKey();

        return [
            'country_ids' => ['sometimes', 'array'],
            'country_ids.*' => ['integer', Rule::exists($this->tableFor('country'), 'id')],
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => ['sometimes', 'string', 'size:3', Rule::unique($this->tableFor('currency'), 'code')->ignore($currencyId)],
            'symbol' => ['nullable', 'string', 'max:10'],
            'exchange_rate' => ['sometimes', 'numeric', 'min:0'],
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
