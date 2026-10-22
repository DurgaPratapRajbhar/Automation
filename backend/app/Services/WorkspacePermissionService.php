<?php

namespace App\Services;

use App\Enums\Permission;
use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\RoleRepositoryInterface;

/**
 * Rules for "who may give which power to whom" inside a workspace.
 * Super admin and the workspace owner pass every check.
 */
class WorkspacePermissionService
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository
    ) {}

    /** @return list<string> */
    public function permissionsOf(User $user, ?Role $role): array
    {
        if ($this->hasFullAccess($user, $role)) {
            return Permission::values();
        }

        return $role ? $this->roleRepository->getPermissionNames($role) : [];
    }

    // An actor cannot give a permission they do not have
    public function ensureCanGrant(User $actor, ?Role $actorRole, array $permissionNames): void
    {
        if ($this->hasFullAccess($actor, $actorRole)) {
            return;
        }

        $missing = array_values(array_diff($permissionNames, $this->permissionsOf($actor, $actorRole)));

        if ($missing !== []) {
            throw new BusinessRuleException(
                __('rbac.cannot_grant', ['permissions' => implode(', ', $missing)]),
                403
            );
        }
    }

    // An actor cannot edit, delete or assign a role more powerful than their own
    public function ensureCanManageRole(User $actor, ?Role $actorRole, Role $targetRole): void
    {
        if ($this->hasFullAccess($actor, $actorRole)) {
            return;
        }

        if ($targetRole->isOwner()) {
            throw new BusinessRuleException(__('rbac.owner_only'), 403);
        }

        $extra = array_diff(
            $this->roleRepository->getPermissionNames($targetRole),
            $this->permissionsOf($actor, $actorRole)
        );

        if ($extra !== []) {
            throw new BusinessRuleException(__('rbac.role_too_powerful'), 403);
        }
    }

    public function isOwnerOrSuperAdmin(User $user, ?Role $role): bool
    {
        return $this->hasFullAccess($user, $role);
    }

    // For the role form: permissions grouped by module
    public function groupedPermissions(): array
    {
        $groups = [];

        foreach (Permission::cases() as $permission) {
            $groups[$permission->module()][] = [
                'slug' => $permission->value,
                'name' => $permission->label(),
            ];
        }

        return array_map(
            fn (string $module, array $permissions) => ['module' => $module, 'permissions' => $permissions],
            array_keys($groups),
            array_values($groups)
        );
    }

    private function hasFullAccess(User $user, ?Role $role): bool
    {
        return $user->isSuperAdmin() || ($role?->isOwner() ?? false);
    }
}
