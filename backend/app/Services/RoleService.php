<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleService
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository,
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

    public function create(
        int $workspaceId,
        Role $actorRole,
        string $name,
        array $permissionSlugs
    ): array {

        $slug = $this->makeUniqueSlug($workspaceId, $name);

        $this->permissionService->ensureCanGrant($actorRole, $permissionSlugs);

        return DB::transaction(function () use ($workspaceId, $name, $slug, $permissionSlugs) {

            $role = $this->roleRepository->create([
                'workspace_id' => $workspaceId,
                'name' => $name,
                'slug' => $slug,
                'is_system' => false,
            ]);

            $this->roleRepository->syncPermissions(
                $role,
                $this->permissionRepository->getIdsBySlugs($permissionSlugs)
            );

            return $this->present($role);
        });
    }

    public function update(
        int $workspaceId,
        Role $actorRole,
        int $roleId,
        ?string $name,
        ?array $permissionSlugs
    ): array {

        $role = $this->findOrFail($workspaceId, $roleId);

        $this->ensureEditable($role, $actorRole);

        $data = [];

        if ($name !== null) {
            $data = [
                'name' => $name,
                'slug' => $this->makeUniqueSlug($workspaceId, $name, $role->id),
            ];
        }

        if ($permissionSlugs !== null) {
            $this->permissionService->ensureCanGrant($actorRole, $permissionSlugs);
        }

        return DB::transaction(function () use ($role, $data, $permissionSlugs) {

            if ($data !== []) {
                $this->roleRepository->update($role, $data);
            }

            if ($permissionSlugs !== null) {
                $this->roleRepository->syncPermissions(
                    $role,
                    $this->permissionRepository->getIdsBySlugs($permissionSlugs)
                );
            }

            return $this->present($role);
        });
    }

    public function delete(int $workspaceId, Role $actorRole, int $roleId): void
    {
        $role = $this->findOrFail($workspaceId, $roleId);

        $this->ensureEditable($role, $actorRole);

        $membersCount = $this->roleRepository->countMembers($role);

        if ($membersCount > 0) {
            throw new BusinessRuleException(
                "This role is assigned to {$membersCount} member(s). Change their role first."
            );
        }

        $this->roleRepository->delete($role);
    }

    private function ensureEditable(Role $role, Role $actorRole): void
    {
        if ($role->is_system) {
            throw new BusinessRuleException('System roles cannot be modified.', 403);
        }

        // Warna koi apne hi role me permissions add karke khud ko promote kar lega
        if ($role->id === $actorRole->id) {
            throw new BusinessRuleException('You cannot modify your own role.', 403);
        }

        $this->permissionService->ensureCanManageRole($actorRole, $role);
    }

    private function makeUniqueSlug(int $workspaceId, string $name, ?int $ignoreRoleId = null): string
    {
        $slug = Str::slug($name);

        if ($slug === '') {
            throw new BusinessRuleException('Role name must contain letters or numbers.');
        }

        if (
            $slug === Role::OWNER_SLUG
            || $this->roleRepository->slugExists($workspaceId, $slug, $ignoreRoleId)
        ) {
            throw new BusinessRuleException('A role with this name already exists.');
        }

        return $slug;
    }

    private function findOrFail(int $workspaceId, int $roleId): Role
    {
        $role = $this->roleRepository->findInWorkspace($workspaceId, $roleId);

        if (!$role) {
            throw new BusinessRuleException('Role not found.', 404);
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
            // Use data already loaded by the repository (with/withCount) — no extra query per role
            'members_count' => $role->users_count ?? $this->roleRepository->countMembers($role),
            'permissions'   => $role->isOwner()
                ? $this->permissionService->permissionsOf($role)    // owner = all permissions
                : $role->permissions->pluck('slug')->all(),

        ];
    }
}
