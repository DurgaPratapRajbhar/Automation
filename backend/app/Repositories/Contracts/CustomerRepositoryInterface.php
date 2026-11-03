<?php

namespace App\Repositories\Contracts;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

interface CustomerRepositoryInterface
{
    // Search by name or phone, newest first
    public function cursorForWorkspace(int $workspaceId, ?string $search, int $perPage): CursorPaginator;

    public function findInWorkspace(int $workspaceId, int $customerId): ?Customer;

    public function create(array $data): Customer;

    public function update(Customer $customer, array $data): Customer;

    public function delete(Customer $customer): void;

    // Customers of the workspace with any of these phone numbers, keyed by phone
    public function findByPhones(int $workspaceId, array $phones): Collection;

    // Everyone in the workspace, or only these ids; the caller decides who may get a campaign
    public function forCampaign(int $workspaceId, ?array $ids): Collection;

    public function countForWorkspace(int $workspaceId): int;

    public function findByPhone(int $workspaceId, string $phone): ?Customer;

    // One phone number per customer within a workspace
    public function phoneExists(int $workspaceId, string $phone, ?int $ignoreCustomerId = null): bool;
}
