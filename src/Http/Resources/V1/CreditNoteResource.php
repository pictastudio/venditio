<?php

namespace PictaStudio\Venditio\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Route;
use PictaStudio\Venditio\Http\Resources\Traits\{CanTransformAttributes, HasAttributesToExclude};

class CreditNoteResource extends JsonResource
{
    use CanTransformAttributes;
    use HasAttributesToExclude;

    public function toArray(Request $request)
    {
        return $this->applyAttributesTransformation(
            collect($this->resolveResourceAttributes())
                ->except($this->getAttributesToExclude())
                ->merge([
                    'pdf_download_url' => $this->resolvePdfDownloadUrl(),
                ])
                ->toArray()
        );
    }

    protected function exclude(): array
    {
        return [
            'rendered_html',
            'template_version',
        ];
    }

    protected function resolvePdfDownloadUrl(): ?string
    {
        $routeName = mb_rtrim((string) config('venditio.routes.api.v1.name'), '.') . '.orders.credit_notes.pdf';

        if (!Route::has($routeName)) {
            return null;
        }

        return route($routeName, [
            'order' => $this->resource->order_id,
            'credit_note' => $this->resource->getKey(),
        ]);
    }
}
