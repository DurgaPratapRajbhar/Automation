<?php

namespace App\Repositories\Contracts;

use App\Models\Workspace;

interface WorkspaceRepositoryInterface
{
    public function create(array $data): Workspace;
}