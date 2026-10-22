<?php

namespace App\Services;

use App\Enums\WorkspaceStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Super admin actions on workspaces.
 */
class AdminWorkspaceService
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaceRepository
    ) {}

    public function list(?string $search, int $perPage): CursorPaginator
    {
        $perPage = max(1, min($perPage, config('pagination.max_per_page')));

        return $this->workspaceRepository
            ->cursorAll($search, $perPage)
            ->through(fn (Workspace $workspace) => $this->present($workspace));
    }

    public function updateStatus(int $workspaceId, WorkspaceStatus $status): array
    {
        $workspace = $this->workspaceRepository->findById($workspaceId);

        if (!$workspace) {
            throw new BusinessRuleException(__('rbac.workspace_not_found'), 404);
        }

        return $this->present(
            $this->workspaceRepository->updateStatus($workspace, $status)->loadCount('users')
        );
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
