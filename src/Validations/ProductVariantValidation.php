<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Concerns\InteractsWithTranslatableRules;
use PictaStudio\Venditio\Validations\Contracts\ProductVariantValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ProductVariantValidation implements ProductVariantValidationRules
{
    use InteractsWithTranslatableRules;

    public function getStoreValidationRules(): array
    {
        return [
            'product_type_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('product_type'), 'id'),
            ],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
            ]),
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'product_type_id' => [
                'sometimes',
                'integer',
                Rule::exists($this->tableFor('product_type'), 'id'),
            ],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
            ]),
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
