<?php

namespace App\Repositories\Eloquent;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

class CustomerRepository implements CustomerRepositoryInterface
{
    public function cursorForWorkspace(int $workspaceId, ?string $search, int $perPage): CursorPaginator
    {
        return Customer::query()
            ->where('workspace_id', $workspaceId)
            ->when($search !== null && $search !== '', function ($query) use ($search) {
                $like = '%'.mb_strtolower($search).'%';

                // LOWER() makes the search case-insensitive on Postgres too
                $query->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhere('phone', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function findInWorkspace(int $workspaceId, int $customerId): ?Customer
    {
        return Customer::where('workspace_id', $workspaceId)->find($customerId);
    }

    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        if ($data !== []) {
            $customer->update($data);
        }

        return $customer->refresh();
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    public function phoneExists(int $workspaceId, string $phone, ?int $ignoreCustomerId = null): bool
    {
        return Customer::where('workspace_id', $workspaceId)
            ->where('phone', $phone)
            ->when($ignoreCustomerId, fn ($query) => $query->where('id', '!=', $ignoreCustomerId))
            ->exists();
    }
}
