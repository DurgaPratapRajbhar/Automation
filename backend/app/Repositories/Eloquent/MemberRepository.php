<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\MemberRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemberRepository implements MemberRepositoryInterface
{
    public function getByWorkspaceId(int $workspaceId): Collection
    {
        return $this->baseQuery($workspaceId)
            ->orderBy('workspace_user.created_at')
            ->get();
    }

    public function findMember(int $workspaceId, int $userId): ?object
    {
        return $this->baseQuery($workspaceId)
            ->where('users.id', $userId)
            ->first();
    }

    public function isMember(int $workspaceId, int $userId): bool
    {
        return DB::table('workspace_user')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function add(int $workspaceId, int $userId, int $roleId): void
    {
        DB::table('workspace_user')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateRole(int $workspaceId, int $userId, int $roleId): void
    {
        DB::table('workspace_user')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->update([
                'role_id' => $roleId,
                'updated_at' => now(),
            ]);
    }

    public function remove(int $workspaceId, int $userId): void
    {
        DB::table('workspace_user')
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->delete();
    }

    public function countByRole(int $workspaceId, int $roleId): int
    {
        return DB::table('workspace_user')
            ->where('workspace_id', $workspaceId)
            ->where('role_id', $roleId)
            ->count();
    }

    private function baseQuery(int $workspaceId): Builder
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
                'roles.is_system as role_is_system',
                'workspace_user.created_at as joined_at',
            ]);
    }
}
