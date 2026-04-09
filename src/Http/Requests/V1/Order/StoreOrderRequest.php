<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Order;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\OrderValidationRules;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(OrderValidationRules $orderValidationRules): array
    {
        return $orderValidationRules->getStoreValidationRules();
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
