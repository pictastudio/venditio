<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Support\CatalogImage;
use PictaStudio\Venditio\Validations\Concerns\{InteractsWithTranslatableRules, ValidatesSeoMetadata};
use PictaStudio\Venditio\Validations\Contracts\ProductCollectionValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ProductCollectionValidation implements ProductCollectionValidationRules
{
    use InteractsWithTranslatableRules;
    use ValidatesSeoMetadata;

    public function getStoreValidationRules(): array
    {
        return [
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => ['sometimes', 'filled', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            ...$this->seoMetadataValidationRules(),
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                'distinct',
                Rule::exists($this->tableFor('tag'), 'id'),
            ],
            'images' => ['sometimes', 'nullable', 'array'],
            'images.*.id' => ['nullable', 'string', 'max:255'],
            'images.*.file' => ['sometimes', 'file', 'image'],
            'images.*.alt' => ['nullable', 'string', 'max:255'],
            'images.*.name' => ['nullable', 'string', 'max:255'],
            'images.*.mimetype' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'images.*.type' => ['nullable', 'string', Rule::in(CatalogImage::TYPES)],
            'active' => ['sometimes', 'boolean'],
            'visible_from' => ['nullable', 'date'],
            'visible_until' => ['nullable', 'date', 'after_or_equal:visible_from'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
                'slug' => ['sometimes', 'filled', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string'],
            ]),
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => ['sometimes', 'filled', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            ...$this->seoMetadataValidationRules(),
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                'distinct',
                Rule::exists($this->tableFor('tag'), 'id'),
            ],
            'images' => ['sometimes', 'nullable', 'array'],
            'images.*.id' => ['nullable', 'string', 'max:255'],
            'images.*.file' => ['sometimes', 'file', 'image'],
            'images.*.alt' => ['nullable', 'string', 'max:255'],
            'images.*.name' => ['nullable', 'string', 'max:255'],
            'images.*.mimetype' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'images.*.type' => ['nullable', 'string', Rule::in(CatalogImage::TYPES)],
            'active' => ['sometimes', 'boolean'],
            'visible_from' => ['nullable', 'date'],
            'visible_until' => ['nullable', 'date', 'after_or_equal:visible_from'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
                'slug' => ['sometimes', 'filled', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string'],
            ]),
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
