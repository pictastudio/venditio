<?php

namespace PictaStudio\Venditio\Validations;

use PictaStudio\Venditio\Validations\Contracts\ProductVariantOptionMediaUploadValidationRules;

class ProductVariantOptionMediaUploadValidation implements ProductVariantOptionMediaUploadValidationRules
{
    public function getUploadValidationRules(): array
    {
        return [
            'images' => ['sometimes', 'nullable', 'array'],
            'images.*.file' => ['required', 'file', 'image'],
            'images.*.alt' => ['nullable', 'string', 'max:255'],
            'images.*.name' => ['nullable', 'string', 'max:255'],
            'images.*.mimetype' => ['nullable', 'string', 'max:255'],
            'images.*.active' => ['nullable', 'boolean'],
            'images.*.thumbnail' => ['nullable', 'boolean'],
            'files' => ['sometimes', 'nullable', 'array'],
            'files.*.file' => ['required', 'file'],
            'files.*.alt' => ['nullable', 'string', 'max:255'],
            'files.*.name' => ['nullable', 'string', 'max:255'],
            'files.*.mimetype' => ['nullable', 'string', 'max:255'],
            'files.*.active' => ['nullable', 'boolean'],
            'files.*.thumbnail' => ['prohibited'],
        ];
    }
}
