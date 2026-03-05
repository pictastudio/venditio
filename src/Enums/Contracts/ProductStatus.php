<?php

namespace PictaStudio\Venditio\Enums\Contracts;

interface ProductStatus
{
    public static function getActiveStatuses(): array;

    public static function getInactiveStatuses(): array;
}
