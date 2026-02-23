<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class UpdateInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['sometimes', 'integer', Rule::exists($this->tableFor('product'), 'id')],
            'currency_id' => ['nullable', 'integer', Rule::exists($this->tableFor('currency'), 'id')],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'stock_reserved' => ['sometimes', 'integer', 'min:0'],
            'stock_min' => ['nullable', 'integer', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'price_includes_tax' => ['sometimes', 'boolean'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
