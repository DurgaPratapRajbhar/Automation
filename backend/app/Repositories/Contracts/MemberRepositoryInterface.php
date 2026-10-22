<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface MemberRepositoryInterface
{
    public function getByWorkspaceId(int $workspaceId): Collection;

    public function findMember(int $workspaceId, int $userId): ?object;

    public function isMember(int $workspaceId, int $userId): bool;

    public function add(int $workspaceId, int $userId, int $roleId): void;

    public function updateRole(int $workspaceId, int $userId, int $roleId): void;

    public function remove(int $workspaceId, int $userId): void;

    public function countByRole(int $workspaceId, int $roleId): int;
}
