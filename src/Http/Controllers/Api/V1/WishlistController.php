<?php

namespace PictaStudio\Venditio\Http\Controllers\Api\V1;

use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use PictaStudio\Venditio\Actions\Wishlists\{AddWishlistItem, CreateWishlist, UpdateWishlist, UpdateWishlistItem};
use PictaStudio\Venditio\Events\{WishlistDeleted, WishlistItemRemoved};
use PictaStudio\Venditio\Http\Controllers\Api\Controller;
use PictaStudio\Venditio\Http\Requests\V1\Wishlist\{StoreWishlistRequest, UpdateWishlistRequest};
use PictaStudio\Venditio\Http\Requests\V1\WishlistItem\{StoreWishlistItemRequest, UpdateWishlistItemRequest};
use PictaStudio\Venditio\Http\Resources\V1\{WishlistItemResource, WishlistResource};
use PictaStudio\Venditio\Models\{Wishlist, WishlistItem};

use function PictaStudio\Venditio\Helpers\Functions\{query, resolve_model};

class WishlistController extends Controller
{
    public function index(): JsonResource|JsonResponse
    {
        $this->authorizeIfConfigured('viewAny', resolve_model('wishlist'));

        $includes = $this->resolveWishlistIncludes();
        $filters = request()->except('include');
        $query = query('wishlist')->with($this->wishlistRelationsForIncludes($includes));
        $this->loadWishlistProductCountIfRequested($query, $includes);

        return WishlistResource::collection(
            $this->applyBaseFilters(
                $query,
                $filters,
                'wishlist',
                $this->wishlistIndexValidationRules()
            )
        );
    }

    public function store(StoreWishlistRequest $request, CreateWishlist $action): JsonResource
    {
        $this->authorizeIfConfigured('create', resolve_model('wishlist'));

        $includes = $this->resolveWishlistIncludes();
        $wishlist = $action->handle($request->validated());
        $this->loadWishlistRelations($wishlist, $includes);

        return WishlistResource::make($wishlist);
    }

    public function show(Wishlist $wishlist): JsonResource
    {
        $this->authorizeIfConfigured('view', $wishlist);

        $includes = $this->resolveWishlistIncludes();
        $this->loadWishlistRelations($wishlist, $includes);

        return WishlistResource::make($wishlist);
    }

    public function update(UpdateWishlistRequest $request, Wishlist $wishlist, UpdateWishlist $action): JsonResource
    {
        $this->authorizeIfConfigured('update', $wishlist);

        $includes = $this->resolveWishlistIncludes();
        $wishlist = $action->handle($wishlist, $request->validated());
        $this->loadWishlistRelations($wishlist, $includes);

        return WishlistResource::make($wishlist);
    }

    public function destroy(Wishlist $wishlist)
    {
        $this->authorizeIfConfigured('delete', $wishlist);

        $wishlist->delete();
        event(new WishlistDeleted($wishlist));

        return response()->noContent();
    }

    public function storeItem(StoreWishlistItemRequest $request, Wishlist $wishlist, AddWishlistItem $action): JsonResource
    {
        $this->authorizeIfConfigured('update', $wishlist);

        $includes = $this->resolveWishlistItemIncludes();
        $wishlistItem = $action->handle($wishlist, $request->validated());
        $wishlistItem->load($this->wishlistItemRelationsForIncludes($includes));

        return WishlistItemResource::make($wishlistItem);
    }

    public function updateItem(
        UpdateWishlistItemRequest $request,
        Wishlist $wishlist,
        WishlistItem $wishlistItem,
        UpdateWishlistItem $action
    ): JsonResource|JsonResponse {
        $this->authorizeIfConfigured('update', $wishlist);

        if (!$this->wishlistItemBelongsToWishlist($wishlistItem, $wishlist)) {
            return $this->errorJsonResponse(
                data: ['wishlist_item_id' => $wishlistItem->getKey()],
                message: 'The wishlist item does not belong to the provided wishlist.',
                status: 422,
            );
        }

        $includes = $this->resolveWishlistItemIncludes();
        $wishlistItem = $action->handle($wishlistItem, $request->validated());
        $wishlistItem->load($this->wishlistItemRelationsForIncludes($includes));

        return WishlistItemResource::make($wishlistItem);
    }

    public function destroyItem(Wishlist $wishlist, WishlistItem $wishlistItem)
    {
        $this->authorizeIfConfigured('update', $wishlist);

        if (!$this->wishlistItemBelongsToWishlist($wishlistItem, $wishlist)) {
            return $this->errorJsonResponse(
                data: ['wishlist_item_id' => $wishlistItem->getKey()],
                message: 'The wishlist item does not belong to the provided wishlist.',
                status: 422,
            );
        }

        $wishlistItem->delete();
        event(new WishlistItemRemoved($wishlistItem));

        return response()->noContent();
    }

    protected function resolveWishlistIncludes(): array
    {
        return $this->resolveIncludes(['user', 'items', 'items.product', 'products', 'products_count']);
    }

    protected function resolveWishlistItemIncludes(): array
    {
        return $this->resolveIncludes(['product']);
    }

    protected function wishlistRelationsForIncludes(array $includes): array
    {
        $relations = [];

        if (in_array('user', $includes, true)) {
            $relations[] = 'user';
        }

        if (in_array('items', $includes, true)) {
            $relations[] = 'items';
        }

        if (in_array('items.product', $includes, true)) {
            $relations[] = 'items.product';
        }

        if (in_array('products', $includes, true)) {
            $relations[] = 'products';
        }

        return $relations;
    }

    protected function wishlistItemRelationsForIncludes(array $includes): array
    {
        return in_array('product', $includes, true) ? ['product'] : [];
    }

    protected function wishlistIndexValidationRules(): array
    {
        $userModel = app(resolve_model('user'));
        $userTable = method_exists($userModel, 'getTableName')
            ? $userModel->getTableName()
            : $userModel->getTable();

        return [
            'user_id' => ['sometimes', 'integer', Rule::exists($userTable, $userModel->getKeyName())],
        ];
    }

    protected function loadWishlistRelations(Model $wishlist, array $includes): void
    {
        $wishlist->load($this->wishlistRelationsForIncludes($includes));
        $this->loadWishlistProductCountIfRequested($wishlist, $includes);
    }

    protected function loadWishlistProductCountIfRequested(Builder|Model $target, array $includes): void
    {
        if (!in_array('products_count', $includes, true)) {
            return;
        }

        if ($target instanceof Builder) {
            $target->withCount('products');

            return;
        }

        $target->loadCount('products');
    }

    protected function wishlistItemBelongsToWishlist(WishlistItem $wishlistItem, Wishlist $wishlist): bool
    {
        return (int) $wishlistItem->getAttribute('wishlist_id') === (int) $wishlist->getKey();
    }
}
