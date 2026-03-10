<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Product;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\ProductVariantOptionMediaUploadValidationRules;

class UploadProductVariantOptionMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(ProductVariantOptionMediaUploadValidationRules $validationRules): array
    {
        return $validationRules->getUploadValidationRules();
    }
}
