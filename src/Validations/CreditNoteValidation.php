<?php

namespace PictaStudio\Venditio\Validations;

use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Validations\Contracts\CreditNoteValidationRules;

use function PictaStudio\Venditio\Helpers\Functions\resolve_model;

class CreditNoteValidation implements CreditNoteValidationRules
{
    public function getStoreValidationRules(): array
    {
        return [
            'return_request_id' => [
                'required',
                'integer',
                Rule::exists($this->tableFor('return_request'), 'id')
                    ->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ];
    }

    public function getUpdateValidationRules(): array
    {
        return [];
    }

    private function tableFor(string $model): string
    {
        return (new (resolve_model($model)))->getTable();
    }
}
