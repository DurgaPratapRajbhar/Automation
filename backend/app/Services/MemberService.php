<?php

namespace App\Services;

use App\Repositories\Contracts\MemberRepositoryInterface;

class MemberService
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository
    ) {}

    public function getMembers(int $workspaceId)
    {
        return $this->memberRepository
            ->getByWorkspaceId($workspaceId);
    }
}