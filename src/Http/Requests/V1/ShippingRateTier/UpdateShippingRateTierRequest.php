<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ShippingRateTier;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\ShippingRateTierValidationRules;

class UpdateShippingRateTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(ShippingRateTierValidationRules $rules): array
    {
        return $rules->getUpdateValidationRules();
    }
}
