<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Country;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $countryId = $this->route('country')?->getKey();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'iso_2' => ['sometimes', 'string', 'size:2', Rule::unique($this->tableFor('country'), 'iso_2')->ignore($countryId)],
            'iso_3' => ['sometimes', 'string', 'size:3', Rule::unique($this->tableFor('country'), 'iso_3')->ignore($countryId)],
            'phone_code' => ['sometimes', 'string', 'max:20'],
            'currency_id' => ['sometimes', 'integer', Rule::exists($this->tableFor('currency'), 'id')],
            'flag_emoji' => ['sometimes', 'string', 'max:50'],
            'capital' => ['sometimes', 'string', 'max:150'],
            'native' => ['nullable', 'string', 'max:150'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
