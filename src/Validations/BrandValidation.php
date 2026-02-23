<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Concerns\InteractsWithTranslatableRules;
use PictaStudio\Venditio\Validations\Contracts\BrandValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class BrandValidation implements BrandValidationRules
{
    use InteractsWithTranslatableRules;

    public function getStoreValidationRules(): array
    {
        $brandTable = $this->tableFor('brand');
        $brandMorphClass = $this->morphClassFor('brand');

        return [
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                Rule::unique($brandTable, 'slug'),
                $this->uniqueTranslatedAttributeValueRule(
                    $brandMorphClass,
                    app()->getLocale(),
                    'slug'
                ),
            ],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
            ]),
            ...$this->translatableLocaleRulesForAttribute('slug', fn (string $locale): array => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                $this->uniqueTranslatedAttributeValueRule($brandMorphClass, $locale, 'slug'),
            ]),
        ];
    }

    public function getUpdateValidationRules(): array
    {
        $brandTable = $this->tableFor('brand');
        $brandMorphClass = $this->morphClassFor('brand');
        $brandId = $this->routeModelKey('brand');

        $slugUniqueRule = Rule::unique($brandTable, 'slug');

        if ($brandId !== null) {
            $slugUniqueRule = $slugUniqueRule->ignore($brandId);
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
                    $brandMorphClass,
                    app()->getLocale(),
                    'slug',
                    $brandId
                ),
            ],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
            ]),
            ...$this->translatableLocaleRulesForAttribute('slug', fn (string $locale): array => [
                'sometimes',
                'filled',
                'string',
                'max:255',
                $this->uniqueTranslatedAttributeValueRule($brandMorphClass, $locale, 'slug', $brandId),
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
