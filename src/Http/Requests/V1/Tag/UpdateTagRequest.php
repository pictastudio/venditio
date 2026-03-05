<?php

namespace PictaStudio\Venditio\Http\Requests\V1\Tag;

use Illuminate\Foundation\Http\FormRequest;
use PictaStudio\Venditio\Http\Requests\V1\Concerns\InteractsWithTranslatableInput;
use PictaStudio\Venditio\Validations\Contracts\TagValidationRules;

class UpdateTagRequest extends FormRequest
{
    use InteractsWithTranslatableInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(TagValidationRules $tagValidationRules): array
    {
        return $tagValidationRules->getUpdateValidationRules();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareTranslatableInput();
        $this->prepareTranslatedSlugInput();
    }
}
