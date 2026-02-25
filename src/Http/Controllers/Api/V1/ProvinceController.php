<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Resources\V1\ProvinceResource;
use PictaStudio\Venditio\Models\Province;

use function PictaStudio\Venditio\Helpers\Functions\query;

class ProvinceController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', Province::class);

        $filters = request()->all();

        $this->validateData($filters, [
            'region_id' => [
                'sometimes',
                'integer',
                Rule::exists((new (config('venditio.models.region')))->getTable(), 'id'),
            ],
        ]);

        return ProvinceResource::collection(
            $this->applyBaseFilters(
                query('province')
                    ->when(
                        isset($filters['region_id']),
                        fn (Builder $builder) => $builder->where('region_id', $filters['region_id']),
                    ),
                $filters,
                'province'
            )
        );
    }

    public function show(Province $province): JsonResource
    {
        $this->authorizeIfConfigured('view', $province);

        return ProvinceResource::make($province);
    }
}
