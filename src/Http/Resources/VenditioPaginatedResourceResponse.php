<?php

namespace PictaStudio\Venditio\Http\Resources;

use Illuminate\Http\Resources\Json\PaginatedResourceResponse;

class VenditioPaginatedResourceResponse extends PaginatedResourceResponse
{
    protected function wrapper()
    {
        if (!config('venditio.routes.api.json_resource_enable_wrapping')) {
            return;
        }

        return parent::wrapper();
    }
}
