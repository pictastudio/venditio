<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ProductCategory;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Http\Requests\V1\Concerns\{InteractsWithTranslatableInput, NormalizesMetadataInput};
use PictaStudio\Venditio\Validations\Contracts\ProductCategoryValidationRules;

class UpdateProductCategoryRequest extends FormRequest
{
    use InteractsWithTranslatableInput;
    use NormalizesMetadataInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(ProductCategoryValidationRules $productCategoryValidationRules): array
    {
        return $productCategoryValidationRules->getUpdateValidationRules();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeMetadataInput();
        $this->prepareTranslatableInput();
        $this->prepareTranslatedSlugInput();
    }
}
