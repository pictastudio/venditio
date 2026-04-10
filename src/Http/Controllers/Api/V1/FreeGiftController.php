<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use PictaStudio\Venditio\Actions\FreeGifts\{CreateFreeGift, UpdateFreeGift};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\FreeGift\{StoreFreeGiftRequest, UpdateFreeGiftRequest};
use PictaStudio\Venditio\Http\Resources\V1\FreeGiftResource;
use PictaStudio\Venditio\Models\FreeGift;

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class FreeGiftController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', resolve_model('free_gift'));

        return FreeGiftResource::collection(
            $this->applyBaseFilters(
                query('free_gift')->with($this->relations()),
                request()->all(),
                'free_gift'
            )
        );
    }

    public function store(StoreFreeGiftRequest $request): JsonResource
    {
        $this->authorizeIfConfigured('create', resolve_model('free_gift'));

        $freeGift = app(CreateFreeGift::class)
            ->handle($request->validated());

        return FreeGiftResource::make($freeGift);
    }

    public function show(FreeGift $freeGift): JsonResource
    {
        $this->authorizeIfConfigured('view', $freeGift);

        return FreeGiftResource::make($freeGift->loadMissing($this->relations()));
    }

    public function update(UpdateFreeGiftRequest $request, FreeGift $freeGift): JsonResource
    {
        $this->authorizeIfConfigured('update', $freeGift);

        $freeGift = app(UpdateFreeGift::class)
            ->handle($freeGift, $request->validated());

        return FreeGiftResource::make($freeGift);
    }

    public function destroy(FreeGift $freeGift)
    {
        $this->authorizeIfConfigured('delete', $freeGift);

        $freeGift->delete();

        return response()->noContent();
    }

    private function relations(): array
    {
        return [
            'qualifyingUsers',
            'qualifyingProducts.inventory',
            'giftProducts.inventory',
        ];
    }
}
