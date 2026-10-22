<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface CustomerRepositoryInterface
{
    // Search by name or phone, newest first
    public function cursorForWorkspace(int $workspaceId, ?string $search, int $perPage): CursorPaginator;

    public function findInWorkspace(int $workspaceId, int $customerId): ?Customer;

    public function create(array $data): Customer;

    public function update(Customer $customer, array $data): Customer;

    public function delete(Customer $customer): void;

    // One phone number per customer within a workspace
    public function phoneExists(int $workspaceId, string $phone, ?int $ignoreCustomerId = null): bool;
}
