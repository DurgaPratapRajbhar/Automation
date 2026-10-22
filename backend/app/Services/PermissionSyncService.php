<?php

namespace App\Services;

use App\Enums\Permission;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes the permissions table match App\Enums\Permission.
 */
class PermissionSyncService
{
    public function __construct(
        private PermissionRepositoryInterface $permissionRepository,
        private RoleRepositoryInterface $roleRepository,
        private PermissionRegistrar $permissionRegistrar
    ) {}

    /** @return array{created: int, deleted: int} */
    public function sync(): array
    {
        $names = Permission::values();

        $created = $this->permissionRepository->createMissing($names);
        $deleted = $this->permissionRepository->deleteExcept($names);

        // Owners always hold every permission, including new ones
        foreach ($this->roleRepository->ownerRoles() as $ownerRole) {
            $this->roleRepository->syncPermissions($ownerRole, $names);
        }

        $this->permissionRegistrar->forgetCachedPermissions();

        return ['created' => $created, 'deleted' => $deleted];
    }
}
