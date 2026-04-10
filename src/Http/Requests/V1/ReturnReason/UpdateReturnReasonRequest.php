<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ReturnReason;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\ReturnReasonValidationRules;

class UpdateReturnReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(ReturnReasonValidationRules $returnReasonValidationRules): array
    {
        return $returnReasonValidationRules->getUpdateValidationRules();
    }
}
