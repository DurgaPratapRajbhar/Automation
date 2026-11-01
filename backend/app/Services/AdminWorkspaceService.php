<?php

namespace App\Services;

use App\Enums\WorkspaceStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Workspace;
use App\Repositories\Contracts\MemberRepositoryInterface;
use App\Repositories\Contracts\RefreshTokenRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Super admin actions on workspaces.
 */
class AdminWorkspaceService
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaceRepository,
        private WorkspaceSetupService $setupService,
        private MemberRepositoryInterface $memberRepository,
        private UserRepositoryInterface $userRepository,
        private RefreshTokenRepositoryInterface $refreshTokens
    ) {}

    public function create(array $data): array
    {
        return $this->present($this->setupService->create($data)->loadCount('users'));
    }

    public function list(?string $search, int $perPage): CursorPaginator
    {
        $perPage = max(1, min($perPage, config('pagination.max_per_page')));

        return $this->workspaceRepository
            ->cursorAll($search, $perPage)
            ->through(fn (Workspace $workspace) => $this->present($workspace));
    }

    // One shop with the people who can log in to it
    public function show(int $workspaceId): array
    {
        $workspace = $this->findOrFail($workspaceId);

        return [
            ...$this->present($workspace->loadCount('users')),
            'members' => $this->memberRepository->getByWorkspaceId($workspace->id)
                ->map(fn (object $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->role_name,
                ])->values()->all(),
        ];
    }

    public function update(int $workspaceId, array $data): array
    {
        $workspace = $this->findOrFail($workspaceId);

        return $this->present($this->workspaceRepository->update($workspace, ['name' => $data['workspace_name']])->loadCount('users'));
    }

    // Admin sets a new password for a shop's user (forgotten password); the user is logged out everywhere
    public function resetMemberPassword(int $workspaceId, int $userId, string $password): void
    {
        $this->findOrFail($workspaceId);

        if (! $this->memberRepository->isMember($workspaceId, $userId)) {
            throw new BusinessRuleException(__('workspace.member_not_found'), 404);
        }

        $user = $this->userRepository->findById($userId);

        if ($user->isSuperAdmin()) {
            throw new BusinessRuleException(__('workspace.cannot_reset_super_admin'));
        }

        $this->userRepository->updatePassword($user, $password);
        $this->refreshTokens->revokeAllForUser($user->id);
    }

    public function updateStatus(int $workspaceId, WorkspaceStatus $status): array
    {
        $workspace = $this->findOrFail($workspaceId);

        return $this->present(
            $this->workspaceRepository->updateStatus($workspace, $status)->loadCount('users')
        );
    }

    private function findOrFail(int $workspaceId): Workspace
    {
        $workspace = $this->workspaceRepository->findById($workspaceId);

        if (! $workspace) {
            throw new BusinessRuleException(__('rbac.workspace_not_found'), 404);
        }

        return $workspace;
    }

    private function present(Workspace $workspace): array
    {
        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'type' => $workspace->type,
            'status' => $workspace->status,
            'members_count' => $workspace->users_count,
            'created_at' => $workspace->created_at,
        ];
    }
}
