<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ProductTag;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Http\Requests\V1\Concerns\InteractsWithTranslatableInput;
use PictaStudio\Venditio\Validations\Contracts\ProductTagValidationRules;

class StoreProductTagRequest extends FormRequest
{
    use InteractsWithTranslatableInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(ProductTagValidationRules $productTagValidationRules): array
    {
        return $productTagValidationRules->getStoreValidationRules();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->hasTranslatableValue('name')) {
                return;
            }

            $validator->errors()->add('name', 'The name field is required.');
        });
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTranslatableInput();
        $this->prepareTranslatedSlugInput();
    }
}
