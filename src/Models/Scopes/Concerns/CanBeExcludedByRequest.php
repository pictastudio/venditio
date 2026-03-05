<?php

namespace PictaStudio\Venditio\Models\Scopes\Concerns;

trait CanBeExcludedByRequest
{
    protected function shouldExcludeScope(?string $specificToggle = null): bool
    {
        $request = request();

        if ($request->boolean('exclude_all_scopes')) {
            return true;
        }

        if ($specificToggle === null) {
            return false;
        }

        return $request->boolean($specificToggle);
    }
}
