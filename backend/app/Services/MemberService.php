<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Repositories\Contracts\MemberRepositoryInterface;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use App\Events\MemberRoleChanged;
use App\Events\MemberRemoved;

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

    /**
     * Existing user ho to workspace me jod do, warna naya user bana ke jodo.
     */
    public function add(
        int $workspaceId,
        Role $actorRole,
        string $email,
        ?string $name,
        ?string $password,
        int $roleId
    ): array {

        $role = $this->findRoleOrFail($workspaceId, $roleId);

        $this->ensureCanAssign($actorRole, $role);

        return DB::transaction(function () use ($workspaceId, $email, $name, $password, $role) {

            $user = $this->userRepository->findByEmail($email);

            if (!$user) {
                if (!$name || !$password) {
                    throw new BusinessRuleException('Name and password are required to create a new user.');
                }

                $user = $this->userRepository->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                ]);
            } elseif ($this->memberRepository->isMember($workspaceId, $user->id)) {
                throw new BusinessRuleException('This user is already a member of this workspace.');
            }

            $this->memberRepository->add($workspaceId, $user->id, $role->id);

            return $this->present(
                $this->memberRepository->findMember($workspaceId, $user->id)
            );
        });
    }

    public function updateRole(
        int $workspaceId,
        Role $actorRole,
        int $actorUserId,
        int $userId,
        int $roleId
    ): array {

        if ($userId === $actorUserId) {
            throw new BusinessRuleException('You cannot change your own role.', 403);
        }

        $member = $this->findMemberOrFail($workspaceId, $userId);
        $currentRole = $this->findRoleOrFail($workspaceId, (int) $member->role_id);

        $this->permissionService->ensureCanManageRole($actorRole, $currentRole);

        $newRole = $this->findRoleOrFail($workspaceId, $roleId);
        if ($newRole->id === $currentRole->id) {
            return $this->present($member);
        }

        $this->ensureCanAssign($actorRole, $newRole);

        if ($currentRole->isOwner() && !$newRole->isOwner()) {
            $this->ensureNotLastOwner($workspaceId, $currentRole);
        }

        $this->memberRepository->updateRole($workspaceId, $userId, $newRole->id);

        MemberRoleChanged::dispatch(
            $workspaceId,
            $actorUserId,
            $userId,
            $currentRole->name,
            $newRole->name,
        );

        return $this->present(
            $this->memberRepository->findMember($workspaceId, $userId)
        );
    }

    public function remove(
        int $workspaceId,
        Role $actorRole,
        int $actorUserId,
        int $userId
    ): void {

        if ($userId === $actorUserId) {
            throw new BusinessRuleException('You cannot remove yourself from the workspace.', 403);
        }

        $member = $this->findMemberOrFail($workspaceId, $userId);
        $currentRole = $this->findRoleOrFail($workspaceId, (int) $member->role_id);

        $this->permissionService->ensureCanManageRole($actorRole, $currentRole);

        if ($currentRole->isOwner()) {
            $this->ensureNotLastOwner($workspaceId, $currentRole);
        }

        $this->memberRepository->remove($workspaceId, $userId);

         MemberRemoved::dispatch(
            $workspaceId,
            $actorUserId,
            $userId,
            $currentRole->name,
        );
    }

    private function ensureCanAssign(Role $actorRole, Role $role): void
    {
        if ($role->isOwner() && !$actorRole->isOwner()) {
            throw new BusinessRuleException('Only an owner can assign the owner role.', 403);
        }

        $this->permissionService->ensureCanManageRole($actorRole, $role);
    }

    private function ensureNotLastOwner(int $workspaceId, Role $ownerRole): void
    {
        if ($this->memberRepository->countByRole($workspaceId, $ownerRole->id) <= 1) {
            throw new BusinessRuleException('A workspace must have at least one owner.');
        }
    }

    private function findRoleOrFail(int $workspaceId, int $roleId): Role
    {
        $role = $this->roleRepository->findInWorkspace($workspaceId, $roleId);

        if (!$role) {
            throw new BusinessRuleException('Selected role does not exist in this workspace.');
        }

        return $role;
    }

    private function findMemberOrFail(int $workspaceId, int $userId): object
    {
        $member = $this->memberRepository->findMember($workspaceId, $userId);

        if (!$member) {
            throw new BusinessRuleException('Member not found.', 404);
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
