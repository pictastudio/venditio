<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\TaxClass\{StoreTaxClassRequest, UpdateTaxClassRequest};
use PictaStudio\Venditio\Http\Resources\V1\TaxClassResource;
use PictaStudio\Venditio\Models\TaxClass;

use function PictaStudio\Venditio\Helpers\Functions\query;

class TaxClassController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', TaxClass::class);

        $includes = request()->query('include', []);

        $this->validateData([
            'include' => $includes,
        ], [
            'include' => ['array'],
            'include.*' => [
                'string',
                Rule::in(['countries']),
            ],
        ]);

        $filters = request()->except('include');

        return TaxClassResource::collection(
            $this->applyBaseFilters(query('tax_class')->with($includes), $filters, 'tax_class')
        );
    }

    public function store(StoreTaxClassRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', TaxClass::class);

        $taxClass = query('tax_class')->create($request->validated());

        return TaxClassResource::make($taxClass);
    }

    public function show(TaxClass $taxClass): JsonResource
    {
        $this->authorizeIfConfigured('view', $taxClass);

        return TaxClassResource::make($taxClass);
    }

    public function update(UpdateTaxClassRequest $request, TaxClass $taxClass): JsonResource
    {
        $this->authorizeIfConfigured('update', $taxClass);

        $taxClass->fill($request->validated());
        $taxClass->save();

        return TaxClassResource::make($taxClass->refresh());
    }

    public function destroy(TaxClass $taxClass)
    {
        $this->authorizeIfConfigured('delete', $taxClass);

        $taxClass->delete();

        return response()->noContent();
    }
}
