<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\CountryTaxClass\{StoreCountryTaxClassRequest, UpdateCountryTaxClassRequest};
use PictaStudio\Venditio\Http\Resources\V1\CountryTaxClassResource;
use PictaStudio\Venditio\Models\CountryTaxClass;

use function PictaStudio\Venditio\Helpers\Functions\query;

class CountryTaxClassController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', CountryTaxClass::class);

        return CountryTaxClassResource::collection(
            $this->applyBaseFilters(query('country_tax_class'), request()->all(), 'country_tax_class')
        );
    }

    public function store(StoreCountryTaxClassRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', CountryTaxClass::class);

        $countryTaxClass = query('country_tax_class')->create($request->validated());

        return CountryTaxClassResource::make($countryTaxClass);
    }

    public function show(CountryTaxClass $countryTaxClass): JsonResource
    {
        $this->authorizeIfConfigured('view', $countryTaxClass);

        return CountryTaxClassResource::make($countryTaxClass);
    }

    public function update(UpdateCountryTaxClassRequest $request, CountryTaxClass $countryTaxClass): JsonResource
    {
        $this->authorizeIfConfigured('update', $countryTaxClass);

        $countryTaxClass->fill($request->validated());
        $countryTaxClass->save();

        return CountryTaxClassResource::make($countryTaxClass->refresh());
    }

    public function destroy(CountryTaxClass $countryTaxClass)
    {
        $this->authorizeIfConfigured('delete', $countryTaxClass);

        $countryTaxClass->delete();

        return response()->noContent();
    }
}
