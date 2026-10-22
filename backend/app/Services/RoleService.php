<?php

namespace App\Services;

use App\Repositories\Contracts\RoleRepositoryInterface;
use Illuminate\Support\Collection;

class RoleService
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository
    ) {}

    public function getRoles(int $workspaceId): Collection
    {
        return $this->roleRepository
            ->getByWorkspaceId($workspaceId);
    }
}