<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\AddressValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class AddressValidation implements AddressValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'addressable_type' => ['sometimes', 'string', 'max:255'],
            'addressable_id' => ['sometimes', 'integer'],
            'country_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('country'), 'id'),
            ],
            'province_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('province'), 'id'),
            ],
            'region_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('region'), 'id'),
            ],
            'municipality_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('municipality'), 'id'),
            ],
            'type' => [
                'required',
                'string',
                Rule::enum(config('venditio.addresses.type_enum')),
            ],
            'is_default' => ['sometimes', 'boolean'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'sex' => ['required', 'string', 'in:m,f'],
            'phone' => ['required', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:25'],
            'fiscal_code' => ['required', 'string', 'max:25'],
            'company_name' => ['nullable', 'string'],
            'address_line_1' => ['required', 'string'],
            'address_line_2' => ['nullable', 'string'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:10'],
            'zip' => ['required', 'string', 'max:10'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'addressable_type' => ['sometimes', 'string', 'max:255'],
            'addressable_id' => ['sometimes', 'integer'],
            'country_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('country'), 'id'),
            ],
            'province_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('province'), 'id'),
            ],
            'region_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('region'), 'id'),
            ],
            'municipality_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('municipality'), 'id'),
            ],
            'type' => [
                'sometimes',
                'string',
                Rule::enum(config('venditio.addresses.type_enum')),
            ],
            'is_default' => ['sometimes', 'boolean'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'sex' => ['sometimes', 'string', 'in:m,f'],
            'phone' => ['sometimes', 'string', 'max:50'],
            'vat_number' => ['nullable', 'string', 'max:25'],
            'fiscal_code' => ['sometimes', 'string', 'max:25'],
            'company_name' => ['nullable', 'string'],
            'address_line_1' => ['sometimes', 'string'],
            'address_line_2' => ['nullable', 'string'],
            'city' => ['sometimes', 'string', 'max:100'],
            'state' => ['sometimes', 'string', 'max:10'],
            'zip' => ['sometimes', 'string', 'max:10'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
