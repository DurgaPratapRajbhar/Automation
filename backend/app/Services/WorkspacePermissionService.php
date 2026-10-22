<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;

/**
 * Workspace ke andar "kaun kya kar sakta hai" ka single source of truth.
 * Owner role sab kuch kar sakta hai; baaki roles sirf assigned permissions.
 */
class WorkspacePermissionService
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository
    ) {}

    public function can(Role $role, string $permissionSlug): bool
    {
        if ($role->isOwner()) {
            return true;
        }

        return $this->roleRepository->hasPermission($role, $permissionSlug);
    }

    public function permissionsOf(Role $role): array
    {
        if ($role->isOwner()) {
            return $this->permissionRepository->getAllSlugs();
        }

        return $this->roleRepository->getPermissionSlugs($role);
    }

    // Jo permission actor ke paas khud nahi hai, wo kisi role ko de nahi sakta
    public function ensureCanGrant(Role $actorRole, array $permissionSlugs): void
    {
        if ($actorRole->isOwner()) {
            return;
        }

        $missing = array_values(array_diff(
            $permissionSlugs,
            $this->permissionsOf($actorRole)
        ));

        if ($missing !== []) {
            throw new BusinessRuleException(
                'You cannot grant permissions you do not have: ' . implode(', ', $missing),
                403
            );
        }
    }

    // Apne se zyada powerful role ko actor na edit/delete kar sakta hai, na assign
    public function ensureCanManageRole(Role $actorRole, Role $targetRole): void
    {
        if ($actorRole->isOwner()) {
            return;
        }

        if ($targetRole->isOwner()) {
            throw new BusinessRuleException('Only an owner can manage the owner role.', 403);
        }

        $extra = array_diff(
            $this->permissionsOf($targetRole),
            $this->permissionsOf($actorRole)
        );

        if ($extra !== []) {
            throw new BusinessRuleException(
                'You cannot manage a role that has more permissions than yours.',
                403
            );
        }
    }

    // Frontend ke checkbox screen ke liye: module wise groups
    public function groupedPermissions(): array
    {
        return $this->permissionRepository->getAll()
            ->groupBy(fn ($permission) => explode('.', $permission->slug)[0])
            ->map(fn ($permissions, $module) => [
                'module' => $module,
                'permissions' => $permissions->map(fn ($permission) => [
                    'slug' => $permission->slug,
                    'name' => $permission->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
