<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\MemberRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
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

    public function add(int $workspaceId, int $userId): void
    {
        DB::table('workspace_user')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'created_at' => now(),
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

    private function baseQuery(int $workspaceId): Builder
    {
        $roles = (new Role)->getTable();
        $userRoles = config('permission.table_names.model_has_roles');
        $userMorph = (new User)->getMorphClass();

        return DB::table('workspace_user')
            ->join('users', 'users.id', '=', 'workspace_user.user_id')
            ->join($userRoles, function (JoinClause $join) use ($userRoles, $userMorph) {
                $join->on("{$userRoles}.model_id", '=', 'workspace_user.user_id')
                    ->on("{$userRoles}.workspace_id", '=', 'workspace_user.workspace_id')
                    ->where("{$userRoles}.model_type", '=', $userMorph);
            })
            ->join($roles, "{$roles}.id", '=', "{$userRoles}.role_id")
            ->where('workspace_user.workspace_id', $workspaceId)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                "{$roles}.id as role_id",
                "{$roles}.name as role_name",
                "{$roles}.slug as role_slug",
                "{$roles}.is_system as role_is_system",
                'workspace_user.created_at as joined_at',
            ]);
    }
}
