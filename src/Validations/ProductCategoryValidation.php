<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Concerns\InteractsWithTranslatableRules;
use PictaStudio\Venditio\Validations\Contracts\ProductCategoryValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ProductCategoryValidation implements ProductCategoryValidationRules
{
    use InteractsWithTranslatableRules;

    public function getStoreValidationRules(): array
    {
        $productCategoryTable = $this->tableFor('product_category');
        $productCategoryMorphClass = $this->morphClassFor('product_category');

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('product_category'), 'id'),
            ],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                Rule::unique($productCategoryTable, 'slug'),
                $this->uniqueTranslatedAttributeValueRule(
                    $productCategoryMorphClass,
                    app()->getLocale(),
                    'slug'
                ),
            ],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
            ]),
            ...$this->translatableLocaleRulesForAttribute('slug', fn (string $locale): array => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                $this->uniqueTranslatedAttributeValueRule($productCategoryMorphClass, $locale, 'slug'),
            ]),
        ];
    }

    public function getUpdateValidationRules(): array
    {
        $productCategoryTable = $this->tableFor('product_category');
        $productCategoryMorphClass = $this->morphClassFor('product_category');
        $productCategoryId = $this->routeModelKey('product_category');

        $slugUniqueRule = Rule::unique($productCategoryTable, 'slug');

        if ($productCategoryId !== null) {
            $slugUniqueRule = $slugUniqueRule->ignore($productCategoryId);
        }

        return [
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists($this->tableFor('product_category'), 'id'),
            ],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                $slugUniqueRule,
                $this->uniqueTranslatedAttributeValueRule(
                    $productCategoryMorphClass,
                    app()->getLocale(),
                    'slug',
                    $productCategoryId
                ),
            ],
            'active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
            ]),
            ...$this->translatableLocaleRulesForAttribute('slug', fn (string $locale): array => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                $this->uniqueTranslatedAttributeValueRule(
                    $productCategoryMorphClass,
                    $locale,
                    'slug',
                    $productCategoryId
                ),
            ]),
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }

    private function morphClassFor(string $model): string
    {
        return (new (resolve_model($model)))->getMorphClass();
    }
}
