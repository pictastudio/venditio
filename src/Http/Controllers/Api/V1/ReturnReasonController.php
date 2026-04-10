<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\ReturnReason\{StoreReturnReasonRequest, UpdateReturnReasonRequest};
use PictaStudio\Venditio\Http\Resources\V1\ReturnReasonResource;
use PictaStudio\Venditio\Models\ReturnReason;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class ReturnReasonController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', resolve_model('return_reason'));

        return ReturnReasonResource::collection(
            $this->applyBaseFilters(query('return_reason'), request()->all(), 'return_reason')
        );
    }

    public function store(StoreReturnReasonRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', resolve_model('return_reason'));

        $returnReason = query('return_reason')->create($request->validated());

        return ReturnReasonResource::make($returnReason->refresh());
    }

    public function show(ReturnReason $returnReason): JsonResource
    {
        $this->authorizeIfConfigured('view', $returnReason);

        return ReturnReasonResource::make($returnReason);
    }

    public function update(UpdateReturnReasonRequest $request, ReturnReason $returnReason): JsonResource
    {
        $this->authorizeIfConfigured('update', $returnReason);

        $returnReason->fill($request->validated());
        $returnReason->save();

        return ReturnReasonResource::make($returnReason->refresh());
    }

    public function destroy(ReturnReason $returnReason)
    {
        $this->authorizeIfConfigured('delete', $returnReason);

        $returnReason->delete();

        return response()->noContent();
    }
}
