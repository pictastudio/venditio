<?php

namespace PictaStudio\Venditio\Http\Requests\V1\CountryTaxClass;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class StoreCountryTaxClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country_id' => ['required', 'integer', Rule::exists($this->tableFor('country'), 'id')],
            'tax_class_id' => ['required', 'integer', Rule::exists($this->tableFor('tax_class'), 'id')],
            'rate' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (!$this->countryTaxClassExists(
                (int) $this->input('country_id'),
                (int) $this->input('tax_class_id')
            )) {
                return;
            }

            $validator->errors()->add(
                'tax_class_id',
                'The country_id and tax_class_id combination has already been taken.'
            );
        });
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }

    private function countryTaxClassExists(int $countryId, int $taxClassId): bool
    {
        return resolve_model('country_tax_class')::query()
            ->where('country_id', $countryId)
            ->where('tax_class_id', $taxClassId)
            ->exists();
    }
}
