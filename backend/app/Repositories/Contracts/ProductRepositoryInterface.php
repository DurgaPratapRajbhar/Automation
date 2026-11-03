<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface
{
    public function create(array $data): Product;

    public function update(Product $product, array $data): Product;

    public function delete(Product $product): void;

    public function findInWorkspace(int $workspaceId, int $productId): ?Product;

    public function cursorForWorkspace(int $workspaceId, ?string $search, int $perPage): CursorPaginator;

    // Take $quantity from the stock if there is enough (a product with no stock number always has enough)
    public function reserve(int $productId, int $quantity): bool;

    // Give back $quantity to the stock (a product with no stock number is not counted)
    public function restock(int $productId, int $quantity): void;

    // Every active product, for an agent to answer from
    public function activeForWorkspace(int $workspaceId): Collection;
}
