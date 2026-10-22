<?php

namespace App\Repositories\Eloquent;

use App\Models\Workspace;
use App\Repositories\Contracts\MemberRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemberRepository implements MemberRepositoryInterface
{
    public function getByWorkspaceId(int $workspaceId): Collection
    {
        return DB::table('workspace_user')
            ->join('users', 'users.id', '=', 'workspace_user.user_id')
            ->join('roles', 'roles.id', '=', 'workspace_user.role_id')
            ->where('workspace_user.workspace_id', $workspaceId)
            ->where('roles.workspace_id', $workspaceId)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'roles.id as role_id',
                'roles.name as role_name',
                'roles.slug as role_slug',
                'workspace_user.created_at as joined_at',
            ])
            ->get();
    }
}