<?php

namespace App\Repositories\Contracts;

use App\Models\Workspace;
use Illuminate\Support\Collection;
interface WorkspaceRepositoryInterface
{
    public function create(array $data): Workspace;
    public function getForUser(int $userId): Collection;
}