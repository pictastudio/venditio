<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\WishlistValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class WishlistValidation implements WishlistValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists($this->tableFor('user'), 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['sometimes', 'filled', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('product'), 'id')],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'user_id' => ['sometimes', 'integer', Rule::exists($this->tableFor('user'), 'id')],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => ['sometimes', 'filled', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('product'), 'id')],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
