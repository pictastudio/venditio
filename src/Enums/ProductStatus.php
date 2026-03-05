<?php

namespace PictaStudio\Venditio\Enums;

use PictaStudio\Venditio\Enums\Contracts\ProductStatus as ProductStatusContract;

enum ProductStatus: string implements ProductStatusContract
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public static function getActiveStatuses(): array
    {
        return [
            self::Published,
        ];
    }

    public static function getInactiveStatuses(): array
    {
        return [
            self::Draft,
            self::Archived,
        ];
    }
}
