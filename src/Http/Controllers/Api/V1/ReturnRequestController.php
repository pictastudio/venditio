<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Actions\Returns\{CreateReturnRequest, DeleteReturnRequest, UpdateReturnRequest};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ReturnRequest\{StoreReturnRequest, UpdateReturnRequest as UpdateReturnRequestRequest};
use PictaStudio\Venditio\Http\Resources\V1\ReturnRequestResource;
use PictaStudio\Venditio\Models\ReturnRequest;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class ReturnRequestController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', resolve_model('return_request'));

        return ReturnRequestResource::collection(
            $this->applyBaseFilters(
                query('return_request')->with($this->relations()),
                request()->all(),
                'return_request'
            )
        );
    }

    public function store(StoreReturnRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', resolve_model('return_request'));

        $returnRequest = app(CreateReturnRequest::class)
            ->handle($request->validated());

        return ReturnRequestResource::make($returnRequest);
    }

    public function show(ReturnRequest $returnRequest): JsonResource
    {
        $this->authorizeIfConfigured('view', $returnRequest);

        return ReturnRequestResource::make($returnRequest->loadMissing($this->relations()));
    }

    public function update(UpdateReturnRequestRequest $request, ReturnRequest $returnRequest): JsonResource
    {
        $this->authorizeIfConfigured('update', $returnRequest);

        $returnRequest = app(UpdateReturnRequest::class)
            ->handle($returnRequest, $request->validated());

        return ReturnRequestResource::make($returnRequest);
    }

    public function destroy(ReturnRequest $returnRequest)
    {
        $this->authorizeIfConfigured('delete', $returnRequest);

        app(DeleteReturnRequest::class)->handle($returnRequest);

        return response()->noContent();
    }

    private function relations(): array
    {
        return [
            'order',
            'user',
            'returnReason',
            'lines.orderLine',
        ];
    }
}
