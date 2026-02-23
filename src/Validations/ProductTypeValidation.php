<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Concerns\InteractsWithTranslatableRules;
use PictaStudio\Venditio\Validations\Contracts\ProductTypeValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ProductTypeValidation implements ProductTypeValidationRules
{
    use InteractsWithTranslatableRules;

    public function getStoreValidationRules(): array
    {
        $productTypeTable = $this->tableFor('product_type');
        $productTypeMorphClass = $this->morphClassFor('product_type');

        return [
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                Rule::unique($productTypeTable, 'slug'),
                $this->uniqueTranslatedAttributeValueRule(
                    $productTypeMorphClass,
                    app()->getLocale(),
                    'slug'
                ),
            ],
            'active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
            ]),
            ...$this->translatableLocaleRulesForAttribute('slug', fn (string $locale): array => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                $this->uniqueTranslatedAttributeValueRule($productTypeMorphClass, $locale, 'slug'),
            ]),
        ];
    }

    public function getUpdateValidationRules(): array
    {
        $productTypeTable = $this->tableFor('product_type');
        $productTypeMorphClass = $this->morphClassFor('product_type');
        $productTypeId = $this->routeModelKey('product_type');

        $slugUniqueRule = Rule::unique($productTypeTable, 'slug');

        if ($productTypeId !== null) {
            $slugUniqueRule = $slugUniqueRule->ignore($productTypeId);
        }

        return [
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                $slugUniqueRule,
                $this->uniqueTranslatedAttributeValueRule(
                    $productTypeMorphClass,
                    app()->getLocale(),
                    'slug',
                    $productTypeId
                ),
            ],
            'active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
            ]),
            ...$this->translatableLocaleRulesForAttribute('slug', fn (string $locale): array => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                $this->uniqueTranslatedAttributeValueRule($productTypeMorphClass, $locale, 'slug', $productTypeId),
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
