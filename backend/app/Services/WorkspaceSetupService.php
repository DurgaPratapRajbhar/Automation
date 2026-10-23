<?php

namespace App\Services;

use App\Enums\WorkspaceStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Workspace;
use App\Repositories\Contracts\MemberRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Creates a shop (workspace) with its owner. Only the super admin does this.
class WorkspaceSetupService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private WorkspaceRepositoryInterface $workspaceRepository,
        private MemberRepositoryInterface $memberRepository,
        private RoleRepositoryInterface $roleRepository,
        private RoleService $roleService
    ) {}

    // The owner is an existing user (by email) or a new one (name + password needed then)
    public function create(array $data): Workspace
    {
        return DB::transaction(function () use ($data) {
            $owner = $this->userRepository->findByEmail($data['owner_email']);

            if (! $owner) {
                if (empty($data['owner_name']) || empty($data['owner_password'])) {
                    throw new BusinessRuleException(__('workspace.owner_details_required'));
                }

                $owner = $this->userRepository->create([
                    'name' => $data['owner_name'],
                    'email' => $data['owner_email'],
                    'password' => $data['owner_password'],
                ]);
            } elseif ($owner->isSuperAdmin()) {
                // Admin and shop accounts stay separate
                throw new BusinessRuleException(__('workspace.owner_is_super_admin'));
            }

            $workspace = $this->workspaceRepository->create([
                'name' => $data['workspace_name'],
                'slug' => Str::slug($data['workspace_name']).'-'.Str::lower(Str::random(6)),
                'type' => $data['workspace_type'] ?? 'business',
                'status' => WorkspaceStatus::Active->value,
            ]);

            $ownerRole = $this->roleService->createSystemRoles($workspace->id);

            $this->memberRepository->add($workspace->id, $owner->id);
            $this->roleRepository->assignToUser($owner, $ownerRole);

            return $workspace;
        });
    }
}
