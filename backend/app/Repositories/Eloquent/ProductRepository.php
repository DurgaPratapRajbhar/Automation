<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductRepository implements ProductRepositoryInterface
{
    public function create(array $data): Product
    {
        // refreshed: the database fills in the defaults
        return Product::create($data)->refresh();
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }

    public function findInWorkspace(int $workspaceId, int $productId): ?Product
    {
        return Product::where('workspace_id', $workspaceId)->find($productId);
    }

    public function cursorForWorkspace(int $workspaceId, ?string $search, int $perPage): CursorPaginator
    {
        return Product::where('workspace_id', $workspaceId)
            ->when($search, fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($perPage);
    }

    public function reserve(int $productId, int $quantity): bool
    {
        return Product::where('id', $productId)
            ->where(fn ($query) => $query->whereNull('stock')->orWhere('stock', '>=', $quantity))
            ->update(['stock' => DB::raw('stock - '.$quantity)]) === 1;
    }

    public function restock(int $productId, int $quantity): void
    {
        Product::where('id', $productId)->whereNotNull('stock')->increment('stock', $quantity);
    }

    public function activeForWorkspace(int $workspaceId): Collection
    {
        return Product::where('workspace_id', $workspaceId)->where('active', true)->orderBy('name')->get();
    }
}
