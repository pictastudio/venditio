<?php

namespace PictaStudio\Venditio\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceResponse;

class VenditioResourceResponse extends ResourceResponse
{
    protected function wrapper()
    {
        if (!config('venditio.routes.api.json_resource_enable_wrapping')) {
            return;
        }

        return parent::wrapper();
    }
}
