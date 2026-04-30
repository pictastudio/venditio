<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ProductCollection;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Http\Requests\V1\Concerns\{InteractsWithTranslatableInput, NormalizesMetadataInput};
use PictaStudio\Venditio\Validations\Contracts\ProductCollectionValidationRules;

class StoreProductCollectionRequest extends FormRequest
{
    use InteractsWithTranslatableInput;
    use NormalizesMetadataInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(ProductCollectionValidationRules $productCollectionValidationRules): array
    {
        return $productCollectionValidationRules->getStoreValidationRules();
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
