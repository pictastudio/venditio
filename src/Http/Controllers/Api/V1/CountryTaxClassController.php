<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Actions\CountryTaxClasses\UpsertMultipleCountryTaxClasses;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\CountryTaxClass\{StoreCountryTaxClassRequest, UpdateCountryTaxClassRequest, UpsertMultipleCountryTaxClassRequest};
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

    public function upsertMultiple(UpsertMultipleCountryTaxClassRequest $request): JsonResource
    {
        $validated = $request->validated();
        $countryTaxClasses = collect($validated['country_tax_classes']);
        $targetTuples = $countryTaxClasses
            ->map(
                fn (array $countryTaxClassPayload): string => $this->countryTaxClassTupleKey(
                    (int) $countryTaxClassPayload['country_id'],
                    (int) $countryTaxClassPayload['tax_class_id']
                )
            )
            ->flip();

        $existingCountryTaxClasses = query('country_tax_class')
            ->whereIn(
                'country_id',
                $countryTaxClasses
                    ->pluck('country_id')
                    ->map(fn (mixed $countryId): int => (int) $countryId)
                    ->unique()
                    ->all()
            )
            ->whereIn(
                'tax_class_id',
                $countryTaxClasses
                    ->pluck('tax_class_id')
                    ->map(fn (mixed $taxClassId): int => (int) $taxClassId)
                    ->unique()
                    ->all()
            )
            ->get()
            ->filter(
                fn (CountryTaxClass $countryTaxClass): bool => $targetTuples->has(
                    $this->countryTaxClassTupleKey(
                        (int) $countryTaxClass->country_id,
                        (int) $countryTaxClass->tax_class_id
                    )
                )
            )
            ->keyBy(
                fn (CountryTaxClass $countryTaxClass): string => $this->countryTaxClassTupleKey(
                    (int) $countryTaxClass->country_id,
                    (int) $countryTaxClass->tax_class_id
                )
            );

        $needsCreateAuthorization = false;

        foreach ($countryTaxClasses as $countryTaxClassPayload) {
            $tupleKey = $this->countryTaxClassTupleKey(
                (int) $countryTaxClassPayload['country_id'],
                (int) $countryTaxClassPayload['tax_class_id']
            );
            $existingCountryTaxClass = $existingCountryTaxClasses->get($tupleKey);

            if ($existingCountryTaxClass instanceof CountryTaxClass) {
                $this->authorizeIfConfigured('update', $existingCountryTaxClass);

                continue;
            }

            $needsCreateAuthorization = true;
        }

        if ($needsCreateAuthorization) {
            $this->authorizeIfConfigured('create', CountryTaxClass::class);
        }

        $upsertedCountryTaxClasses = app(UpsertMultipleCountryTaxClasses::class)
            ->handle($validated['country_tax_classes']);

        return CountryTaxClassResource::collection($upsertedCountryTaxClasses);
    }

    public function destroy(CountryTaxClass $countryTaxClass)
    {
        $this->authorizeIfConfigured('delete', $countryTaxClass);

        $countryTaxClass->delete();

        return response()->noContent();
    }

    private function countryTaxClassTupleKey(int $countryId, int $taxClassId): string
    {
        return $countryId . ':' . $taxClassId;
    }
}
