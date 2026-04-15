<?php

namespace PictaStudio\Venditio\Http\Requests\V1\CreditNote;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Validations\Contracts\CreditNoteValidationRules;

class StoreCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(CreditNoteValidationRules $creditNoteValidationRules): array
    {
        return $creditNoteValidationRules->getStoreValidationRules();
    }
}
