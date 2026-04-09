<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Cart;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\CartValidationRules;

class StoreCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(CartValidationRules $cartValidationRules): array
    {
        return $cartValidationRules->getStoreValidationRules();
    }

    protected function prepareForValidation(): void
    {
        if (!$this->user()) {
            return;
        }

        $this->merge([
            'user_id' => $this->user()->getKey(),
        ]);
    }
}
