<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ShippingZone;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\ShippingZoneValidationRules;

class StoreShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(ShippingZoneValidationRules $rules): array
    {
        return $rules->getStoreValidationRules();
    }
}
