<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\RoleTemplateRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleService
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository,
        private RoleTemplateRepositoryInterface $templateRepository,
        private WorkspacePermissionService $permissionService
    ) {}

    public function getRoles(int $workspaceId): array
    {
        return $this->roleRepository
            ->getByWorkspaceId($workspaceId)
            ->map(fn (Role $role) => $this->present($role))
            ->all();
    }

    public function getRole(int $workspaceId, int $roleId): array
    {
        return $this->present($this->findOrFail($workspaceId, $roleId));
    }

    public function create(int $workspaceId, User $actor, ?Role $actorRole, string $name, array $permissionNames): array
    {
        $slug = $this->makeUniqueSlug($workspaceId, $name);

        $this->permissionService->ensureCanGrant($actor, $actorRole, $permissionNames);

        return DB::transaction(function () use ($workspaceId, $name, $slug, $permissionNames) {
            $role = $this->roleRepository->create([
                'workspace_id' => $workspaceId,
                'name' => $name,
                'slug' => $slug,
                'is_system' => false,
            ]);

            $this->roleRepository->syncPermissions($role, $permissionNames);

            return $this->present($role);
        });
    }

    public function update(
        int $workspaceId,
        User $actor,
        ?Role $actorRole,
        int $roleId,
        ?string $name,
        ?array $permissionNames
    ): array {
        $role = $this->findOrFail($workspaceId, $roleId);

        $this->ensureEditable($role, $actor, $actorRole);

        $data = $name === null ? [] : [
            'name' => $name,
            'slug' => $this->makeUniqueSlug($workspaceId, $name, $role->id),
        ];

        if ($permissionNames !== null) {
            $this->permissionService->ensureCanGrant($actor, $actorRole, $permissionNames);
        }

        return DB::transaction(function () use ($role, $data, $permissionNames) {
            if ($data !== []) {
                $this->roleRepository->update($role, $data);
            }

            if ($permissionNames !== null) {
                $this->roleRepository->syncPermissions($role, $permissionNames);
            }

            return $this->present($role->refresh());
        });
    }

    public function delete(int $workspaceId, User $actor, ?Role $actorRole, int $roleId): void
    {
        $role = $this->findOrFail($workspaceId, $roleId);

        $this->ensureEditable($role, $actor, $actorRole);

        $membersCount = $this->roleRepository->countMembers($role);

        if ($membersCount > 0) {
            throw new BusinessRuleException(__('rbac.role_in_use', ['count' => $membersCount]));
        }

        $this->roleRepository->delete($role);
    }

    // New workspace: Owner (all permissions) + a copy of each super admin role template
    public function createSystemRoles(int $workspaceId): Role
    {
        $allPermissions = $this->permissionRepository->getAllNames();

        $owner = $this->roleRepository->create([
            'workspace_id' => $workspaceId,
            'name' => __('rbac.owner_role_name'),
            'slug' => Role::OWNER_SLUG,
            'is_system' => true,
        ]);

        $this->roleRepository->syncPermissions($owner, $allPermissions);

        foreach ($this->templateRepository->all() as $template) {
            $role = $this->roleRepository->create([
                'workspace_id' => $workspaceId,
                'name' => $template->name,
                'slug' => $template->slug,
                'is_system' => false,
            ]);

            // Skip permissions whose routes were removed since the template was saved
            $this->roleRepository->syncPermissions($role, array_values(array_intersect($template->permissions, $allPermissions)));
        }

        return $owner;
    }

    private function ensureEditable(Role $role, User $actor, ?Role $actorRole): void
    {
        if ($role->is_system) {
            throw new BusinessRuleException(__('rbac.system_role_locked'), 403);
        }

        // Otherwise anyone could promote themselves by editing their own role
        if ($actorRole !== null && $role->id === $actorRole->id) {
            throw new BusinessRuleException(__('rbac.cannot_edit_own_role'), 403);
        }

        $this->permissionService->ensureCanManageRole($actor, $actorRole, $role);
    }

    private function makeUniqueSlug(int $workspaceId, string $name, ?int $ignoreRoleId = null): string
    {
        $slug = Str::slug($name);

        if ($slug === '') {
            throw new BusinessRuleException(__('rbac.invalid_role_name'));
        }

        if ($slug === Role::OWNER_SLUG || $this->roleRepository->slugExists($workspaceId, $slug, $ignoreRoleId)) {
            throw new BusinessRuleException(__('rbac.role_exists'));
        }

        return $slug;
    }

    private function findOrFail(int $workspaceId, int $roleId): Role
    {
        $role = $this->roleRepository->findInWorkspace($workspaceId, $roleId);

        if (! $role) {
            throw new BusinessRuleException(__('rbac.role_not_found'), 404);
        }

        return $role;
    }

    private function present(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'is_system' => $role->is_system,
            'is_owner' => $role->isOwner(),
            'members_count' => $role->users_count ?? $this->roleRepository->countMembers($role),
            'permissions' => $role->isOwner()
                ? $this->permissionRepository->getAllNames()
                : $this->roleRepository->getPermissionNames($role),
        ];
    }
}
