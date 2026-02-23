<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ShippingRate;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\ShippingRateValidationRules;

class UpdateShippingRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(ShippingRateValidationRules $rules): array
    {
        return $rules->getUpdateValidationRules();
    }
}
