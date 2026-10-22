<?php

namespace App\Repositories\Eloquent;

use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoleRepository implements RoleRepositoryInterface
{
    public function create(array $data): Role
    {
        return Role::create($data);
    }

    public function getByWorkspaceId(int $workspaceId): Collection
    {
        return Role::where('workspace_id', $workspaceId)
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('id')
            ->get();
    }

    public function findInWorkspace(int $workspaceId, int $roleId): ?Role
    {
        return Role::where('workspace_id', $workspaceId)->find($roleId);
    }

    public function findForUser(int $workspaceId, int $userId): ?Role
    {
        $roles = (new Role)->getTable();
        $userRoles = config('permission.table_names.model_has_roles');

        return Role::query()
            ->join($userRoles, "{$userRoles}.role_id", '=', "{$roles}.id")
            ->where("{$userRoles}.workspace_id", $workspaceId)
            ->where("{$userRoles}.model_type", (new User)->getMorphClass())
            ->where("{$userRoles}.model_id", $userId)
            ->where("{$roles}.workspace_id", $workspaceId)
            ->select("{$roles}.*")
            ->first();
    }

    public function slugExists(int $workspaceId, string $slug, ?int $ignoreRoleId = null): bool
    {
        return Role::where('workspace_id', $workspaceId)
            ->where('slug', $slug)
            ->when($ignoreRoleId, fn ($query) => $query->where('id', '!=', $ignoreRoleId))
            ->exists();
    }

    public function update(Role $role, array $data): Role
    {
        $role->update($data);

        return $role;
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    public function syncPermissions(Role $role, array $permissionNames): void
    {
        // Spatie clears its permission cache here
        $role->syncPermissions($permissionNames);
    }

    public function getPermissionNames(Role $role): array
    {
        return $role->permissions()
            ->orderBy($role->permissions()->getRelated()->getQualifiedKeyName())
            ->pluck('name')
            ->all();
    }

    public function countMembers(Role $role): int
    {
        return DB::table(config('permission.table_names.model_has_roles'))
            ->where('role_id', $role->id)
            ->count();
    }

    public function assignToUser(User $user, Role $role): void
    {
        // Spatie reads the team (workspace) from global state
        setPermissionsTeamId($role->workspace_id);
        $user->unsetRelation('roles');

        $user->syncRoles([$role]);
    }

    public function removeFromUser(User $user, int $workspaceId): void
    {
        setPermissionsTeamId($workspaceId);
        $user->unsetRelation('roles');

        $user->syncRoles([]);
    }

    public function ownerRoles(): Collection
    {
        return Role::where('slug', Role::OWNER_SLUG)
            ->where('is_system', true)
            ->get();
    }
}
