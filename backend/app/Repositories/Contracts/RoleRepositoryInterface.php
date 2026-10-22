<?php

namespace App\Repositories\Contracts;

use App\Models\Role;
use Illuminate\Support\Collection;

interface RoleRepositoryInterface
{
    public function create(array $data): Role;

    public function getByWorkspaceId(int $workspaceId): Collection;
}