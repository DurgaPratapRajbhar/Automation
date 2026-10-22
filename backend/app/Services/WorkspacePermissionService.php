<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Rules for "who may give which power to whom" inside a workspace.
 * Super admin and the workspace owner pass every check.
 */
class WorkspacePermissionService
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository
    ) {}

    public function permissionsOf(User $user, ?Role $role): array
    {
        if ($this->isOwnerOrSuperAdmin($user, $role)) {
            return $this->permissionRepository->getAllNames();
        }

        return $role ? $this->roleRepository->getPermissionNames($role) : [];
    }

    // An actor cannot give a permission they do not have
    public function ensureCanGrant(User $actor, ?Role $actorRole, array $permissionNames): void
    {
        if ($this->isOwnerOrSuperAdmin($actor, $actorRole)) {
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
        if ($this->isOwnerOrSuperAdmin($actor, $actorRole)) {
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
        return $user->isSuperAdmin() || ($role?->isOwner() ?? false);
    }

    // Permission tree for the role form: module -> its permissions
    public function permissionTree(): array
    {
        return $this->permissionRepository->getAllByModule()
            ->groupBy('module')
            ->map(fn ($permissions, $module) => [
                'module' => $module,
                'label' => Str::headline($module),
                'permissions' => $permissions->map(fn (Permission $permission) => [
                    'name' => $permission->name,
                    'label' => Str::headline(Str::afterLast($permission->name, '.')),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
