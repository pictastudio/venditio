<?php

namespace PictaStudio\Venditio\Http\Requests\V1\ReturnRequest;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Http\Requests\V1\ReturnRequest\Concerns\ValidatesReturnRequestPayload;
use PictaStudio\Venditio\Validations\Contracts\ReturnRequestValidationRules;

class UpdateReturnRequest extends FormRequest
{
    use ValidatesReturnRequestPayload;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(ReturnRequestValidationRules $returnRequestValidationRules): array
    {
        return $returnRequestValidationRules->getUpdateValidationRules();
    }
}
