<?php

namespace App\Repositories\Eloquent;

use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;

class WorkspaceRepository implements WorkspaceRepositoryInterface
{
    public function create(array $data): Workspace
    {
        return Workspace::create($data);
    }
}