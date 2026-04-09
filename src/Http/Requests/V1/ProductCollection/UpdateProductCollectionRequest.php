<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ProductCollection;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Http\Requests\V1\Concerns\InteractsWithTranslatableInput;
use PictaStudio\Venditio\Validations\Contracts\ProductCollectionValidationRules;

class UpdateProductCollectionRequest extends FormRequest
{
    use InteractsWithTranslatableInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(ProductCollectionValidationRules $productCollectionValidationRules): array
    {
        return $productCollectionValidationRules->getUpdateValidationRules();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTranslatableInput();
        $this->prepareTranslatedSlugInput();
    }
}
