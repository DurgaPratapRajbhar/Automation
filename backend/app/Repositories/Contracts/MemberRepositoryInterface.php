<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface MemberRepositoryInterface
{
    // Members with their role in this workspace
    public function getByWorkspaceId(int $workspaceId): Collection;

    public function findMember(int $workspaceId, int $userId): ?object;

    public function isMember(int $workspaceId, int $userId): bool;

    // Adds membership only; the role is assigned through RoleRepository
    public function add(int $workspaceId, int $userId): void;

    public function remove(int $workspaceId, int $userId): void;
}
