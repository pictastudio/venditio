<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Resources\V1\MunicipalityResource;
use PictaStudio\Venditio\Models\Municipality;

use function PictaStudio\Venditio\Helpers\Functions\query;

class MunicipalityController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', Municipality::class);

        $filters = request()->all();

        $this->validateData($filters, [
            'province_id' => [
                'sometimes',
                'integer',
                Rule::exists((new (config('venditio.models.province')))->getTable(), 'id'),
            ],
        ]);

        return MunicipalityResource::collection(
            $this->applyBaseFilters(
                query('municipality')
                    ->when(
                        isset($filters['province_id']),
                        fn (Builder $builder) => $builder->where('province_id', $filters['province_id']),
                    ),
                $filters,
                'municipality'
            )
        );
    }

    public function show(Municipality $municipality): JsonResource
    {
        $this->authorizeIfConfigured('view', $municipality);

        return MunicipalityResource::make($municipality);
    }
}
