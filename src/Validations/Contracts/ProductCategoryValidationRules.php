<?php

namespace PictaStudio\Venditio\Validations\Contracts;

interface ProductCategoryValidationRules extends ProvidesValidationRules
{
    public function getBulkUpdateValidationRules(): array;
}
