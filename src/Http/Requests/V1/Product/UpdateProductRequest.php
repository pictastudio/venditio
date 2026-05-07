<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Product;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Http\Requests\V1\Concerns\{InteractsWithTranslatableInput, NormalizesMetadataInput};
use PictaStudio\Venditio\Validations\Contracts\ProductValidationRules;

class UpdateProductRequest extends FormRequest
{
    use InteractsWithTranslatableInput;
    use NormalizesMetadataInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(ProductValidationRules $productValidationRules): array
    {
        return $productValidationRules->getUpdateValidationRules();
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = $this->route('product');

            if (!is_object($product) || !method_exists($product, 'getKey')) {
                return;
            }

            $relatedProductIds = $this->input('related_product_ids');

            if (!is_array($relatedProductIds)) {
                return;
            }

            $productKey = (string) $product->getKey();

            foreach ($relatedProductIds as $relatedProductId) {
                if ((string) $relatedProductId !== $productKey) {
                    continue;
                }

                $validator->errors()->add('related_product_ids', 'A product cannot be related to itself.');

                return;
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeMetadataInput();
        $this->prepareTranslatableInput();
        $this->prepareTranslatedSlugInput();
    }
}
