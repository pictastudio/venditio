<?php

namespace PictaStudio\Venditio\Http\Requests\V1\FreeGift;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\FreeGiftValidationRules;

class StoreFreeGiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(FreeGiftValidationRules $freeGiftValidationRules): array
    {
        return $freeGiftValidationRules->getStoreValidationRules();
    }
}
