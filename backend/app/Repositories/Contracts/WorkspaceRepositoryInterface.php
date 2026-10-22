<?php

namespace App\Repositories\Contracts;

use App\Enums\WorkspaceStatus;
use App\Models\Workspace;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

interface WorkspaceRepositoryInterface
{
    public function create(array $data): Workspace;

    public function findById(int $workspaceId): ?Workspace;

    // Active workspaces of a user, with the user's role in each
    public function getForUser(int $userId): Collection;

    // Super admin list: every workspace, newest first
    public function cursorAll(?string $search, int $perPage): CursorPaginator;

    public function updateStatus(Workspace $workspace, WorkspaceStatus $status): Workspace;

    public function update(Workspace $workspace, array $data): Workspace;
}
