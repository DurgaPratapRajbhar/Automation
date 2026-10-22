<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface MemberRepositoryInterface
{
    public function getByWorkspaceId(int $workspaceId): Collection;
}