<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Collection;

class RoleRepository implements RoleRepositoryInterface
{
    public function create(array $data): Role
    {
        return Role::create($data);
    }

    public function getByWorkspaceId(int $workspaceId): Collection
    {
        return Role::where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->get();
    }
}