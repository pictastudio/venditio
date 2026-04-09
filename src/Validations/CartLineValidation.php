<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\CartLineValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CartLineValidation implements CartLineValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'lines' => ['required', 'array'],
            'lines.*.product_id' => ['required', 'integer', Rule::exists($this->tableFor('product'), 'id')],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'lines' => ['required', 'array'],
            'lines.*.id' => ['required', 'integer', Rule::exists($this->tableFor('cart_line'), 'id')],
            // 'lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
