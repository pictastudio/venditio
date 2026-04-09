<?php

namespace PictaStudio\Venditio\Validations;

use BackedEnum;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\OrderValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class OrderValidation implements OrderValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'cart_id' => [
                'required',
                'integer',
                Rule::exists($this->tableFor('cart'), 'id'),
            ],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('user'), 'id'),
            ],
            'shipping_status_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('shipping_status'), 'id'),
            ],
            'shipping_method_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('shipping_method'), 'id'),
            ],
            'shipping_zone_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('shipping_zone'), 'id'),
            ],
            'status' => [
                'sometimes',
                'string',
                Rule::enum(config('venditio.order.status_enum')),
            ],
            'tracking_code' => ['nullable', 'string', 'max:150'],
            'tracking_link' => ['nullable', 'string', 'max:2048'],
            'last_tracked_at' => ['nullable', 'date'],
            'courier_code' => ['nullable', 'string', 'max:50'],
            'sub_total_taxable' => ['sometimes', 'numeric', 'min:0'],
            'sub_total_tax' => ['sometimes', 'numeric', 'min:0'],
            'sub_total' => ['sometimes', 'numeric', 'min:0'],
            'shipping_fee' => ['sometimes', 'numeric', 'min:0'],
            'specific_weight' => ['sometimes', 'numeric', 'min:0'],
            'volumetric_weight' => ['sometimes', 'numeric', 'min:0'],
            'chargeable_weight' => ['sometimes', 'numeric', 'min:0'],
            'payment_fee' => ['sometimes', 'numeric', 'min:0'],
            'discount_code' => ['nullable', 'string', 'max:255'],
            'discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'total_final' => ['sometimes', 'numeric', 'min:0'],
            'user_first_name' => ['sometimes', 'string', 'max:255'],
            'user_last_name' => ['sometimes', 'string', 'max:255'],
            'user_email' => ['sometimes', 'email', 'max:255'],
            'addresses' => ['sometimes', 'array'],
            ...$this->getAddressValidationRulesFromEnum(),
            'customer_notes' => ['nullable', 'string'],
            'admin_notes' => ['nullable', 'string'],
            'approved_at' => ['nullable', 'date'],
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
            $key . '.sdi' => ['nullable', 'string', 'max:255'],
            $key . '.pec' => ['nullable', 'email', 'max:255'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
