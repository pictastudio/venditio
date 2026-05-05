<?php

namespace PictaStudio\Venditio\Http\Requests\V1\WishlistItem;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\WishlistItemValidationRules;

class StoreWishlistItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(WishlistItemValidationRules $wishlistItemValidationRules): array
    {
        return $wishlistItemValidationRules->getStoreValidationRules();
    }
}
