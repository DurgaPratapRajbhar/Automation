<?php

namespace App\Services;

use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Support\Facades\Cache;

// Decides if the AI may answer for a shop now: the shop is active, its trial has not ended, and a shop
// on trial has not used up its answers for today (every answer costs us money). Paid shops have no limit.
class AiUsageService
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaceRepository
    ) {}

    // $counts = false: only check, do not use up one of today's answers (a helper that runs beside the reply)
    public function allowed(int $workspaceId, bool $counts = true): bool
    {
        $workspace = $this->workspaceRepository->findById($workspaceId);

        if (! $workspace || $workspace->status !== 'active' || $workspace->trialExpired()) {
            return false;
        }

        if ($workspace->trial_ends_at === null) {
            return true;
        }

        $key = "ai-answers:{$workspace->id}:".now()->format('Y-m-d');
        Cache::add($key, 0, now()->endOfDay());

        return ($counts ? Cache::increment($key) : Cache::get($key, 0) + 1) <= config('trial.ai_daily_limit');
    }
}
