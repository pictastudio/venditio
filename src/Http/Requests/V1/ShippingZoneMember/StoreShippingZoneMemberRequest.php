<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ShippingZoneMember;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\ShippingZoneMemberValidationRules;

class StoreShippingZoneMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(ShippingZoneMemberValidationRules $rules): array
    {
        return $rules->getStoreValidationRules();
    }
}
