<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\PriceListPriceValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class PriceListPriceValidation implements PriceListPriceValidationRules
{
    public function getBulkUpsertValidationRules(): array
    {
        return [
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.product_id' => ['required', 'integer', Rule::exists($this->tableFor('product'), 'id')],
            'prices.*.price_list_id' => ['required', 'integer', Rule::exists($this->tableFor('price_list'), 'id')],
            'prices.*.price' => ['required', 'numeric', 'min:0'],
            'prices.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'prices.*.price_includes_tax' => ['sometimes', 'boolean'],
            'prices.*.is_default' => ['sometimes', 'boolean'],
            'prices.*.metadata' => ['nullable', 'array'],
        ];
    }

    public function getStoreValidationRules(): array
    {
        $productId = (int) request('product_id');

        return [
            'product_id' => ['required', 'integer', Rule::exists($this->tableFor('product'), 'id')],
            'price_list_id' => [
                'required',
                'integer',
                Rule::exists($this->tableFor('price_list'), 'id'),
                Rule::unique($this->tableFor('price_list_price'), 'price_list_id')
                    ->where(fn ($query) => $query->where('product_id', $productId))
                    ->withoutTrashed(),
            ],
            'price' => ['required', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'price_includes_tax' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        $priceListPrice = request()->route('price_list_price');
        $priceListPriceId = $priceListPrice?->getKey();
        $productId = (int) (request('product_id') ?? $priceListPrice?->product_id);

        return [
            'product_id' => ['sometimes', 'integer', Rule::exists($this->tableFor('product'), 'id')],
            'price_list_id' => [
                'sometimes',
                'integer',
                Rule::exists($this->tableFor('price_list'), 'id'),
                Rule::unique($this->tableFor('price_list_price'), 'price_list_id')
                    ->where(fn ($query) => $query->where('product_id', $productId))
                    ->withoutTrashed()
                    ->ignore($priceListPriceId),
            ],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'price_includes_tax' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
