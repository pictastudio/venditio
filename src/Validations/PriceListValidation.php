<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\PriceListValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class PriceListValidation implements PriceListValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique($this->tableFor('price_list'), 'name')
                    ->withoutTrashed(),
            ],
            'code' => ['nullable', 'string', 'max:255', Rule::unique($this->tableFor('price_list'), 'code')->withoutTrashed()],
            'active' => ['sometimes', 'boolean'],
            'allow_discounts' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        $priceList = request()->route('price_list');
        $priceListId = $priceList?->getKey();

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique($this->tableFor('price_list'), 'name')
                    ->withoutTrashed()
                    ->ignore($priceListId),
            ],
            'code' => ['nullable', 'string', 'max:255', Rule::unique($this->tableFor('price_list'), 'code')->withoutTrashed()->ignore($priceListId)],
            'active' => ['sometimes', 'boolean'],
            'allow_discounts' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
