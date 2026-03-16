<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Support\CatalogImage;
use PictaStudio\Venditio\Validations\Concerns\InteractsWithTranslatableRules;
use PictaStudio\Venditio\Validations\Contracts\ProductCategoryValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class ProductCategoryValidation implements ProductCategoryValidationRules
{
    use InteractsWithTranslatableRules;

    public function getStoreValidationRules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('product_category'), 'id'),
            ],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => ['sometimes', 'filled', 'string', 'max:255'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                'distinct',
                Rule::exists($this->tableFor('tag'), 'id'),
            ],
            'abstract' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'images' => ['sometimes', 'nullable', 'array'],
            'images.*.id' => ['nullable', 'string', 'max:255'],
            'images.*.file' => ['sometimes', 'file', 'image'],
            'images.*.alt' => ['nullable', 'string', 'max:255'],
            'images.*.name' => ['nullable', 'string', 'max:255'],
            'images.*.mimetype' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'images.*.type' => ['required', 'string', 'distinct', Rule::in(CatalogImage::TYPES)],
            'active' => ['sometimes', 'boolean'],
            'show_in_menu' => ['sometimes', 'boolean'],
            'in_evidence' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'visible_from' => ['nullable', 'date'],
            'visible_until' => ['nullable', 'date', 'after_or_equal:visible_from'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
                'slug' => ['sometimes', 'filled', 'string', 'max:255'],
                'abstract' => ['sometimes', 'nullable', 'string'],
                'description' => ['sometimes', 'nullable', 'string'],
            ]),
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists($this->tableFor('product_category'), 'id'),
            ],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => ['sometimes', 'filled', 'string', 'max:255'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'integer',
                'distinct',
                Rule::exists($this->tableFor('tag'), 'id'),
            ],
            'abstract' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'images' => ['sometimes', 'nullable', 'array'],
            'images.*.id' => ['nullable', 'string', 'max:255'],
            'images.*.file' => ['sometimes', 'file', 'image'],
            'images.*.alt' => ['nullable', 'string', 'max:255'],
            'images.*.name' => ['nullable', 'string', 'max:255'],
            'images.*.mimetype' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'images.*.type' => ['required', 'string', 'distinct', Rule::in(CatalogImage::TYPES)],
            'active' => ['sometimes', 'boolean'],
            'show_in_menu' => ['sometimes', 'boolean'],
            'in_evidence' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'visible_from' => ['nullable', 'date'],
            'visible_until' => ['nullable', 'date', 'after_or_equal:visible_from'],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
                'slug' => ['sometimes', 'filled', 'string', 'max:255'],
                'abstract' => ['sometimes', 'nullable', 'string'],
                'description' => ['sometimes', 'nullable', 'string'],
            ]),
        ];
    }

    public function getBulkUpdateValidationRules(): array
    {
        return [
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists($this->tableFor('product_category'), 'id'),
            ],
            'categories.*.parent_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists($this->tableFor('product_category'), 'id'),
            ],
            'categories.*.sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
