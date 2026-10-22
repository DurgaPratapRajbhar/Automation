<?php

namespace App\Repositories\Contracts;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    public function create(array $data): Role;

    // Roles of a workspace with their permissions and member count
    public function getByWorkspaceId(int $workspaceId): Collection;

    public function findInWorkspace(int $workspaceId, int $roleId): ?Role;

    // The role a user has in a workspace (one role per workspace)
    public function findForUser(int $workspaceId, int $userId): ?Role;

    public function slugExists(int $workspaceId, string $slug, ?int $ignoreRoleId = null): bool;

    public function update(Role $role, array $data): Role;

    public function delete(Role $role): void;

    public function syncPermissions(Role $role, array $permissionNames): void;

    public function getPermissionNames(Role $role): array;

    public function countMembers(Role $role): int;

    // Replaces the user's role in the role's workspace
    public function assignToUser(User $user, Role $role): void;

    public function removeFromUser(User $user, int $workspaceId): void;

    // Owner roles of all workspaces (they always get every permission)
    public function ownerRoles(): Collection;
}
