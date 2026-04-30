<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Brand;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Http\Requests\V1\Concerns\{InteractsWithTranslatableInput, NormalizesMetadataInput};
use PictaStudio\Venditio\Validations\Contracts\BrandValidationRules;

class StoreBrandRequest extends FormRequest
{
    use InteractsWithTranslatableInput;
    use NormalizesMetadataInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(BrandValidationRules $brandValidationRules): array
    {
        return $brandValidationRules->getStoreValidationRules();
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
        $this->normalizeMetadataInput();
        $this->prepareTranslatableInput();
        $this->prepareTranslatedSlugInput();
    }
}
