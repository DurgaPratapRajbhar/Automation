<?php

namespace App\Repositories\Contracts;

use App\Models\Role;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    public function create(array $data): Role;

    public function getByWorkspaceId(int $workspaceId): Collection;

    public function findInWorkspace(int $workspaceId, int $roleId): ?Role;

    public function slugExists(int $workspaceId, string $slug, ?int $ignoreRoleId = null): bool;

    public function update(Role $role, array $data): Role;

    public function delete(Role $role): void;

    public function syncPermissions(Role $role, array $permissionIds): void;

    public function getPermissionSlugs(Role $role): array;

    public function hasPermission(Role $role, string $permissionSlug): bool;

    public function countMembers(Role $role): int;
}
