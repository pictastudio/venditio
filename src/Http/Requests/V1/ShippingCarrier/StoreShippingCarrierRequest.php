<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ShippingCarrier;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\ShippingCarrierValidationRules;

class StoreShippingCarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(ShippingCarrierValidationRules $rules): array
    {
        return $rules->getStoreValidationRules();
    }
}
