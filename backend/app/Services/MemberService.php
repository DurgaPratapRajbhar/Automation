<?php

namespace App\Services;

use App\Events\MemberRemoved;
use App\Events\MemberRoleChanged;
use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\User;
use App\Repositories\Contracts\MemberRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;

class MemberService
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private RoleRepositoryInterface $roleRepository,
        private UserRepositoryInterface $userRepository,
        private WorkspacePermissionService $permissionService
    ) {}

    public function getMembers(int $workspaceId): array
    {
        return $this->memberRepository
            ->getByWorkspaceId($workspaceId)
            ->map(fn (object $member) => $this->present($member))
            ->all();
    }

    // Adds an existing user, or creates the user first
    public function add(
        int $workspaceId,
        User $actor,
        ?Role $actorRole,
        string $email,
        ?string $name,
        ?string $password,
        int $roleId
    ): array {
        $role = $this->findRoleOrFail($workspaceId, $roleId);

        $this->ensureCanAssign($actor, $actorRole, $role);

        return DB::transaction(function () use ($workspaceId, $email, $name, $password, $role) {
            $user = $this->userRepository->findByEmail($email);

            if (! $user) {
                if (! $name || ! $password) {
                    throw new BusinessRuleException(__('rbac.name_and_password_required'));
                }

                $user = $this->userRepository->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                ]);
            } elseif ($this->memberRepository->isMember($workspaceId, $user->id)) {
                throw new BusinessRuleException(__('rbac.already_member'));
            }

            $this->memberRepository->add($workspaceId, $user->id);
            $this->roleRepository->assignToUser($user, $role);

            return $this->present($this->memberRepository->findMember($workspaceId, $user->id));
        });
    }

    public function updateRole(int $workspaceId, User $actor, ?Role $actorRole, int $userId, int $roleId): array
    {
        if ($userId === $actor->id) {
            throw new BusinessRuleException(__('rbac.cannot_change_own_role'), 403);
        }

        $member = $this->findMemberOrFail($workspaceId, $userId);
        $currentRole = $this->findRoleOrFail($workspaceId, (int) $member->role_id);

        $this->permissionService->ensureCanManageRole($actor, $actorRole, $currentRole);

        $newRole = $this->findRoleOrFail($workspaceId, $roleId);

        if ($newRole->id === $currentRole->id) {
            return $this->present($member);
        }

        $this->ensureCanAssign($actor, $actorRole, $newRole);

        if ($currentRole->isOwner() && ! $newRole->isOwner()) {
            $this->ensureNotLastOwner($currentRole);
        }

        $this->roleRepository->assignToUser($this->userRepository->findById($userId), $newRole);

        MemberRoleChanged::dispatch($workspaceId, $actor->id, $userId, $currentRole->name, $newRole->name);

        return $this->present($this->memberRepository->findMember($workspaceId, $userId));
    }

    public function remove(int $workspaceId, User $actor, ?Role $actorRole, int $userId): void
    {
        if ($userId === $actor->id) {
            throw new BusinessRuleException(__('rbac.cannot_remove_self'), 403);
        }

        $member = $this->findMemberOrFail($workspaceId, $userId);
        $currentRole = $this->findRoleOrFail($workspaceId, (int) $member->role_id);

        $this->permissionService->ensureCanManageRole($actor, $actorRole, $currentRole);

        if ($currentRole->isOwner()) {
            $this->ensureNotLastOwner($currentRole);
        }

        DB::transaction(function () use ($workspaceId, $userId) {
            $this->roleRepository->removeFromUser($this->userRepository->findById($userId), $workspaceId);
            $this->memberRepository->remove($workspaceId, $userId);
        });

        MemberRemoved::dispatch($workspaceId, $actor->id, $userId, $currentRole->name);
    }

    private function ensureCanAssign(User $actor, ?Role $actorRole, Role $role): void
    {
        if ($role->isOwner() && ! $this->permissionService->isOwnerOrSuperAdmin($actor, $actorRole)) {
            throw new BusinessRuleException(__('rbac.owner_assign_only'), 403);
        }

        $this->permissionService->ensureCanManageRole($actor, $actorRole, $role);
    }

    private function ensureNotLastOwner(Role $ownerRole): void
    {
        if ($this->roleRepository->countMembers($ownerRole) <= 1) {
            throw new BusinessRuleException(__('rbac.last_owner'));
        }
    }

    private function findRoleOrFail(int $workspaceId, int $roleId): Role
    {
        $role = $this->roleRepository->findInWorkspace($workspaceId, $roleId);

        if (! $role) {
            throw new BusinessRuleException(__('rbac.role_not_in_workspace'));
        }

        return $role;
    }

    private function findMemberOrFail(int $workspaceId, int $userId): object
    {
        $member = $this->memberRepository->findMember($workspaceId, $userId);

        if (! $member) {
            throw new BusinessRuleException(__('rbac.member_not_found'), 404);
        }

        return $member;
    }

    private function present(object $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => [
                'id' => $member->role_id,
                'name' => $member->role_name,
                'slug' => $member->role_slug,
                'is_owner' => (bool) $member->role_is_system && $member->role_slug === Role::OWNER_SLUG,
            ],
            'joined_at' => $member->joined_at,
        ];
    }
}
