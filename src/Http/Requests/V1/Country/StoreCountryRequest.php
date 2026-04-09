<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Country;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class StoreCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'iso_2' => ['required', 'string', 'size:2', Rule::unique($this->tableFor('country'), 'iso_2')],
            'iso_3' => ['required', 'string', 'size:3', Rule::unique($this->tableFor('country'), 'iso_3')],
            'phone_code' => ['required', 'string', 'max:20'],
            'currency_id' => ['required', 'integer', Rule::exists($this->tableFor('currency'), 'id')],
            'flag_emoji' => ['required', 'string', 'max:50'],
            'capital' => ['required', 'string', 'max:150'],
            'native' => ['nullable', 'string', 'max:150'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
