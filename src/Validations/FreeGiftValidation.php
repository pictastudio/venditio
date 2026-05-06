<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Enums\{FreeGiftMode, FreeGiftProductMatchMode, FreeGiftSelectionMode};
use PictaStudio\Venditio\Validations\Contracts\FreeGiftValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class FreeGiftValidation implements FreeGiftValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'mode' => ['required', Rule::enum(FreeGiftMode::class)],
            'selection_mode' => ['sometimes', Rule::enum(FreeGiftSelectionMode::class)],
            'allow_decline' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'minimum_cart_subtotal' => ['nullable', 'numeric', 'min:0'],
            'maximum_cart_subtotal' => ['nullable', 'numeric', 'gte:minimum_cart_subtotal'],
            'minimum_cart_quantity' => ['nullable', 'integer', 'min:0'],
            'maximum_cart_quantity' => ['nullable', 'integer', 'gte:minimum_cart_quantity'],
            'product_match_mode' => ['sometimes', Rule::enum(FreeGiftProductMatchMode::class)],
            'qualifying_user_ids' => ['sometimes', 'array'],
            'qualifying_user_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('user'), 'id')],
            'qualifying_product_ids' => ['sometimes', 'array'],
            'qualifying_product_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('product'), 'id')],
            'gift_product_ids' => ['sometimes', 'array', 'min:1'],
            'gift_product_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('product'), 'id')],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'mode' => ['sometimes', Rule::enum(FreeGiftMode::class)],
            'selection_mode' => ['sometimes', Rule::enum(FreeGiftSelectionMode::class)],
            'allow_decline' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'minimum_cart_subtotal' => ['nullable', 'numeric', 'min:0'],
            'maximum_cart_subtotal' => ['nullable', 'numeric', 'gte:minimum_cart_subtotal'],
            'minimum_cart_quantity' => ['nullable', 'integer', 'min:0'],
            'maximum_cart_quantity' => ['nullable', 'integer', 'gte:minimum_cart_quantity'],
            'product_match_mode' => ['sometimes', Rule::enum(FreeGiftProductMatchMode::class)],
            'qualifying_user_ids' => ['sometimes', 'array'],
            'qualifying_user_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('user'), 'id')],
            'qualifying_product_ids' => ['sometimes', 'array'],
            'qualifying_product_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('product'), 'id')],
            'gift_product_ids' => ['sometimes', 'array', 'min:1'],
            'gift_product_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('product'), 'id')],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
