<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\ShippingZoneMemberValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ShippingZoneMemberValidation implements ShippingZoneMemberValidationRules
{
    private const ALLOWED_TYPES = ['country', 'region', 'province', 'municipality'];

    public function getStoreValidationRules(): array
    {
        return [
            'shipping_zone_id' => [
                'required',
                'integer',
                Rule::exists($this->tableFor('shipping_zone'), 'id'),
            ],
            'zoneable_type' => ['required', 'string', Rule::in(self::ALLOWED_TYPES)],
            'zoneable_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!$this->zoneableExists($value)) {
                        $fail('The selected zoneable_id is invalid for the given zoneable_type.');
                    }
                },
            ],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'shipping_zone_id' => [
                'sometimes',
                'integer',
                Rule::exists($this->tableFor('shipping_zone'), 'id'),
            ],
            'zoneable_type' => ['sometimes', 'string', Rule::in(self::ALLOWED_TYPES)],
            'zoneable_id' => [
                'sometimes',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (!$this->zoneableExists($value)) {
                        $fail('The selected zoneable_id is invalid for the given zoneable_type.');
                    }
                },
            ],
        ];
    }

    private function zoneableExists(mixed $value): bool
    {
        $type = (string) request()->input('zoneable_type');

        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return false;
        }

        $model = resolve_model($type);

        return $model::query()->whereKey((int) $value)->exists();
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
