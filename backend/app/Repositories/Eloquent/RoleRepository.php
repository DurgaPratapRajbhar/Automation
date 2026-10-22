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
            ->with('permissions:id,slug')
            ->withCount('users')
            ->orderBy('id')
            ->get();
    }

    public function findInWorkspace(int $workspaceId, int $roleId): ?Role
    {
        return Role::where('workspace_id', $workspaceId)
            ->find($roleId);
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

    public function syncPermissions(Role $role, array $permissionIds): void
    {
        $role->permissions()->sync($permissionIds);
    }

    public function getPermissionSlugs(Role $role): array
    {
        return $role->permissions()
            ->orderBy('permissions.id')
            ->pluck('slug')
            ->all();
    }

    public function hasPermission(Role $role, string $permissionSlug): bool
    {
        return $role->permissions()
            ->where('slug', $permissionSlug)
            ->exists();
    }

    public function countMembers(Role $role): int
    {
        return $role->users()->count();
    }
}
