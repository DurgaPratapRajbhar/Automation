<?php

namespace App\Repositories\Eloquent;

use App\Enums\WorkspaceStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkspaceRepository implements WorkspaceRepositoryInterface
{
    public function create(array $data): Workspace
    {
        return Workspace::create($data);
    }

    public function findById(int $workspaceId): ?Workspace
    {
        return Workspace::find($workspaceId);
    }

    public function getForUser(int $userId): Collection
    {
        $roles = (new Role)->getTable();
        $userRoles = config('permission.table_names.model_has_roles');
        $userMorph = (new User)->getMorphClass();

        return DB::table('workspace_user')
            ->join('workspaces', 'workspaces.id', '=', 'workspace_user.workspace_id')
            ->join($userRoles, function (JoinClause $join) use ($userRoles, $userMorph) {
                $join->on("{$userRoles}.model_id", '=', 'workspace_user.user_id')
                    ->on("{$userRoles}.workspace_id", '=', 'workspace_user.workspace_id')
                    ->where("{$userRoles}.model_type", '=', $userMorph);
            })
            ->join($roles, "{$roles}.id", '=', "{$userRoles}.role_id")
            ->where('workspace_user.user_id', $userId)
            ->where('workspaces.status', WorkspaceStatus::Active->value)
            ->orderBy('workspace_user.created_at')
            ->select([
                'workspaces.id',
                'workspaces.name',
                'workspaces.slug',
                'workspaces.type',
                "{$roles}.id as role_id",
                "{$roles}.name as role_name",
                "{$roles}.slug as role_slug",
                "{$roles}.is_system as role_is_system",
            ])
            ->get();
    }

    public function cursorAll(?string $search, int $perPage): CursorPaginator
    {
        return Workspace::query()
            ->when($search !== null && $search !== '', function ($query) use ($search) {
                $query->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($search) . '%']);
            })
            ->withCount('users')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function updateStatus(Workspace $workspace, WorkspaceStatus $status): Workspace
    {
        $workspace->update(['status' => $status->value]);

        return $workspace;
    }
}
