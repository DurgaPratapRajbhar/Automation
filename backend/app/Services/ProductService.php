<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

// What the business sells: name, price, how many are left
class ProductService
{
    public function __construct(private ProductRepositoryInterface $products) {}

    public function list(int $workspaceId, ?string $search, int $perPage): CursorPaginator
    {
        $perPage = max(1, min($perPage, config('pagination.max_per_page')));

        return $this->products
            ->cursorForWorkspace($workspaceId, $search, $perPage)
            ->through(fn (Product $product) => $this->present($product));
    }

    public function create(int $workspaceId, array $data): array
    {
        return $this->present($this->products->create([...$data, 'workspace_id' => $workspaceId]));
    }

    public function update(int $workspaceId, int $productId, array $data): array
    {
        return $this->present($this->products->update($this->findOrFail($workspaceId, $productId), $data));
    }

    public function delete(int $workspaceId, int $productId): void
    {
        $this->products->delete($this->findOrFail($workspaceId, $productId));
    }

    private function findOrFail(int $workspaceId, int $productId): Product
    {
        return $this->products->findInWorkspace($workspaceId, $productId)
            ?? throw new BusinessRuleException(__('products.not_found'), 404);
    }

    private function present(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'unit' => $product->unit,
            'stock' => $product->stock,
            'duration_minutes' => $product->duration_minutes,
            'active' => $product->active,
            'available' => $product->available(),
        ];
    }
}
