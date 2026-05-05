<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Wishlist;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Http\Requests\V1\Concerns\NormalizesMetadataInput;
use PictaStudio\Venditio\Validations\Contracts\WishlistValidationRules;

class StoreWishlistRequest extends FormRequest
{
    use NormalizesMetadataInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(WishlistValidationRules $wishlistValidationRules): array
    {
        return $wishlistValidationRules->getStoreValidationRules();
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeMetadataInput();
    }
}
