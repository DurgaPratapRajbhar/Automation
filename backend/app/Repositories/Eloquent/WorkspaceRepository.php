<?php

namespace App\Repositories\Eloquent;

use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class WorkspaceRepository implements WorkspaceRepositoryInterface
{
    public function create(array $data): Workspace
    {
        return Workspace::create($data);
    }

    public function getForUser(int $userId): Collection
    {
        return DB::table('workspace_user')
            ->join('workspaces', 'workspaces.id', '=', 'workspace_user.workspace_id')
            ->join('roles', 'roles.id', '=', 'workspace_user.role_id')
            ->where('workspace_user.user_id', $userId)
            ->where('workspaces.status', 'active')
            ->orderBy('workspace_user.created_at')
            ->select([
                'workspaces.id',
                'workspaces.name',
                'workspaces.slug',
                'workspaces.type',
                'roles.id as role_id',
                'roles.name as role_name',
                'roles.slug as role_slug',
                'roles.is_system as role_is_system',
            ])
            ->get();
    }
}