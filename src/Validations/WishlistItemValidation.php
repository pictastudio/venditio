<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\WishlistItemValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class WishlistItemValidation implements WishlistItemValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists($this->tableFor('product'), 'id')],
            'notes' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
