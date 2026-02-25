<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Resources\V1\RegionResource;
use PictaStudio\Venditio\Models\Region;

use function PictaStudio\Venditio\Helpers\Functions\query;

class RegionController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', Region::class);

        $filters = request()->all();

        $this->validateData($filters, [
            'country_id' => [
                'sometimes',
                'integer',
                Rule::exists((new (config('venditio.models.country')))->getTable(), 'id'),
            ],
        ]);

        return RegionResource::collection(
            $this->applyBaseFilters(
                query('region')
                    ->when(
                        isset($filters['country_id']),
                        fn (Builder $builder) => $builder->where('country_id', $filters['country_id']),
                    ),
                $filters,
                'region'
            )
        );
    }

    public function show(Region $region): JsonResource
    {
        $this->authorizeIfConfigured('view', $region);

        return RegionResource::make($region);
    }
}
