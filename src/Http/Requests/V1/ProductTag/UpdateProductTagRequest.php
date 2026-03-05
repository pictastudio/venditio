<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ProductTag;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Http\Requests\V1\Concerns\InteractsWithTranslatableInput;
use PictaStudio\Venditio\Validations\Contracts\ProductTagValidationRules;

class UpdateProductTagRequest extends FormRequest
{
    use InteractsWithTranslatableInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(ProductTagValidationRules $productTagValidationRules): array
    {
        return $productTagValidationRules->getUpdateValidationRules();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTranslatableInput();
        $this->prepareTranslatedSlugInput();
    }
}
