<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Address\{StoreAddressRequest, UpdateAddressRequest};
use PictaStudio\Venditio\Http\Resources\V1\AddressResource;
use PictaStudio\Venditio\Models\Address;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class AddressController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', resolve_model('address'));

        $filters = request()->all();

        return AddressResource::collection(
            $this->applyBaseFilters(query('address'), $filters, 'address')
        );
    }

    public function store(StoreAddressRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', resolve_model('address'));

        $payload = $request->validated();
        $addressable = auth()->guard()->user();

        if ($addressable) {
            $address = $addressable->addresses()->create(
                Arr::except($payload, ['addressable_type', 'addressable_id'])
            );
        } else {
            $addressable = $this->resolveGuestAddressable($payload);
            $address = $addressable->addresses()->create(
                Arr::except($payload, ['addressable_type', 'addressable_id'])
            );
        }

        return AddressResource::make($address);
    }

    public function show(Address $address): JsonResource
    {
        $this->authorizeIfConfigured('view', $address);

        return AddressResource::make($address);
    }

    public function update(UpdateAddressRequest $request, Address $address): JsonResource
    {
        $this->authorizeIfConfigured('update', $address);

        $address->fill(Arr::except($request->validated(), ['addressable_type', 'addressable_id']));
        $address->save();

        return AddressResource::make($address->refresh());
    }

    public function destroy(Address $address)
    {
        $this->authorizeIfConfigured('delete', $address);

        $address->delete();

        return response()->noContent();
    }

    protected function resolveGuestAddressable(array $payload): Model
    {
        if (!config('venditio.addresses.allow_guest_addressable_assignment')) {
            throw ValidationException::withMessages([
                'addressable' => ['Guest address creation is disabled. Authenticate the request or opt in via configuration.'],
            ]);
        }

        if (!isset($payload['addressable_type'], $payload['addressable_id'])) {
            throw ValidationException::withMessages([
                'addressable' => ['addressable_type and addressable_id are required when no authenticated user is available.'],
            ]);
        }

        $allowedModels = collect(config('venditio.addresses.guest_addressable_models', []))
            ->filter(fn (mixed $model) => is_string($model) && filled($model))
            ->values();

        if (!$allowedModels->contains($payload['addressable_type'])) {
            throw ValidationException::withMessages([
                'addressable_type' => ['The selected addressable_type is not allowed for guest address creation.'],
            ]);
        }

        $addressableModel = Relation::getMorphedModel($payload['addressable_type']);

        if (!is_string($addressableModel) || !is_a($addressableModel, Model::class, true)) {
            throw ValidationException::withMessages([
                'addressable_type' => ['The selected addressable_type is invalid.'],
            ]);
        }

        $addressable = $addressableModel::query()->find($payload['addressable_id']);

        if (!$addressable instanceof Model) {
            throw ValidationException::withMessages([
                'addressable_id' => ['The selected addressable_id is invalid.'],
            ]);
        }

        return $addressable;
    }
}
