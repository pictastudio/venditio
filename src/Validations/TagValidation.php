<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Concerns\InteractsWithTranslatableRules;
use PictaStudio\Venditio\Validations\Contracts\TagValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class TagValidation implements TagValidationRules
{
    use InteractsWithTranslatableRules;

    public function getStoreValidationRules(): array
    {
        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('tag'), 'id'),
            ],
            'product_type_id' => [
                'nullable',
                'integer',
                Rule::exists($this->tableFor('product_type'), 'id'),
            ],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => ['sometimes', 'filled', 'string', 'max:255'],
            'abstract' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'img_thumb' => ['sometimes', 'nullable', 'array'],
            'img_thumb.file' => ['required_with:img_thumb', 'file', 'image'],
            'img_thumb.alt' => ['nullable', 'string', 'max:255'],
            'img_thumb.name' => ['nullable', 'string', 'max:255'],
            'img_cover' => ['sometimes', 'nullable', 'array'],
            'img_cover.file' => ['required_with:img_cover', 'file', 'image'],
            'img_cover.alt' => ['nullable', 'string', 'max:255'],
            'img_cover.name' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'show_in_menu' => ['sometimes', 'boolean'],
            'in_evidence' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'visible_from' => ['nullable', 'date'],
            'visible_until' => ['nullable', 'date', 'after_or_equal:visible_from'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('tag'), 'id')],
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
                Rule::exists($this->tableFor('tag'), 'id'),
            ],
            'product_type_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists($this->tableFor('product_type'), 'id'),
            ],
            'name' => ['sometimes', 'filled', 'string', 'max:255'],
            'slug' => ['sometimes', 'filled', 'string', 'max:255'],
            'abstract' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
            'img_thumb' => ['sometimes', 'nullable', 'array'],
            'img_thumb.file' => ['required_with:img_thumb', 'file', 'image'],
            'img_thumb.alt' => ['nullable', 'string', 'max:255'],
            'img_thumb.name' => ['nullable', 'string', 'max:255'],
            'img_cover' => ['sometimes', 'nullable', 'array'],
            'img_cover.file' => ['required_with:img_cover', 'file', 'image'],
            'img_cover.alt' => ['nullable', 'string', 'max:255'],
            'img_cover.name' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'show_in_menu' => ['sometimes', 'boolean'],
            'in_evidence' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'visible_from' => ['nullable', 'date'],
            'visible_until' => ['nullable', 'date', 'after_or_equal:visible_from'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'distinct', Rule::exists($this->tableFor('tag'), 'id')],
            ...$this->translatableLocaleRules([
                'name' => ['sometimes', 'filled', 'string', 'max:255'],
                'slug' => ['sometimes', 'filled', 'string', 'max:255'],
                'abstract' => ['sometimes', 'nullable', 'string'],
                'description' => ['sometimes', 'nullable', 'string'],
            ]),
        ];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
