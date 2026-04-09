<?php

namespace PictaStudio\Venditio\Http\Resources;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\{AbstractCursorPaginator, AbstractPaginator};

class VenditioAnonymousResourceCollection extends AnonymousResourceCollection
{
    public function toResponse($request)
    {
        if ($this->resource instanceof AbstractPaginator || $this->resource instanceof AbstractCursorPaginator) {
            return $this->preparePaginatedResponse($request);
        }

        return (new VenditioResourceResponse($this))->toResponse($request);
    }

    protected function preparePaginatedResponse($request)
    {
        if ($this->preserveAllQueryParameters) {
            $this->resource->appends($request->query());
        } elseif ($this->queryParameters !== null) {
            $this->resource->appends($this->queryParameters);
        }

        return (new VenditioPaginatedResourceResponse($this))->toResponse($request);
    }
}
