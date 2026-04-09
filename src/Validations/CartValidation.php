<?php

namespace PictaStudio\Venditio\Validations;

use BackedEnum;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\CartValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CartValidation implements CartValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', Rule::exists($this->tableFor('user'), 'id')],
            'user_first_name' => ['nullable', 'string', 'max:255'],
            'user_last_name' => ['nullable', 'string', 'max:255'],
            'user_email' => ['nullable', 'email', 'max:255'],
            'discount_code' => ['nullable', 'string'],
            'addresses' => ['nullable', 'array'],
            ...$this->getAddressValidationRulesFromEnum(),
            'lines' => ['sometimes', 'array'],
            'lines.*.product_id' => ['required_with:lines', 'integer', Rule::exists($this->tableFor('product'), 'id')],
            'lines.*.qty' => ['required_with:lines.*.product_id', 'integer', 'min:1'],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            // 'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_first_name' => ['nullable', 'string', 'max:255'],
            'user_last_name' => ['nullable', 'string', 'max:255'],
            'user_email' => ['nullable', 'email', 'max:255'],
            'discount_code' => ['nullable', 'string'],
            'addresses' => ['nullable', 'array'],
            ...$this->getAddressValidationRulesFromEnum(),
            'lines' => ['sometimes', 'array'],
            'lines.*.product_id' => ['required_with:lines', 'integer', Rule::exists($this->tableFor('product'), 'id')],
            'lines.*.qty' => ['required_with:lines.*.product_id', 'integer', 'min:1'],
        ];
    }

    protected function getAddressValidationRulesFromEnum(): array
    {
        /** @var BackedEnum $addressTypeEnum */
        $addressTypeEnum = config('venditio.addresses.type_enum');

        $rules = [];
        foreach ($addressTypeEnum::cases() as $case) {
            $rules = [
                ...$rules,
                'addresses.' . $case->value => ['sometimes', 'array'],
                ...$this->getAddressValidationRules('addresses.' . $case->value),
            ];
        }

        return $rules;
    }

    protected function getAddressValidationRules(string $key): array
    {
        return [
            $key . '.country_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('country'), 'id'),
            ],
            $key . '.province_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('province'), 'id'),
            ],
            $key . '.first_name' => ['nullable', 'string', 'max:255'],
            $key . '.last_name' => ['nullable', 'string', 'max:255'],
            $key . '.email' => ['nullable', 'email', 'max:255'],
            $key . '.sex' => ['nullable', 'string', 'in:m,f'],
            $key . '.phone' => ['nullable', 'string', 'max:50'],
            $key . '.vat_number' => ['nullable', 'string', 'max:25'],
            $key . '.fiscal_code' => ['nullable', 'string', 'max:25'],
            $key . '.sdi' => ['nullable', 'string', 'max:255'],
            $key . '.pec' => ['nullable', 'email', 'max:255'],
            $key . '.company_name' => ['nullable', 'string'],
            $key . '.address_line_1' => ['nullable', 'string'],
            $key . '.address_line_2' => ['nullable', 'string'],
            $key . '.city' => ['nullable', 'string', 'max:100'],
            $key . '.state' => ['nullable', 'string', 'max:10'],
            $key . '.zip' => ['nullable', 'string', 'max:10'],
            $key . '.birth_date' => ['nullable', 'date'],
            $key . '.birth_place' => ['nullable', 'string', 'max:100'],
            $key . '.notes' => ['nullable', 'string'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
