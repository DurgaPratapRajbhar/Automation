<?php

namespace App\Services;

use App\Enums\ActionStatus;
use App\Repositories\Contracts\ActionRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\PromiseRepositoryInterface;

// "Today at a glance" for the owner: what needs attention first
class DailySummaryService
{
    public function __construct(
        private PromiseRepositoryInterface $promises,
        private ConversationRepositoryInterface $conversations,
        private ActionRepositoryInterface $actions,
    ) {}

    /** The counts that need the owner today */
    public function get(int $workspaceId): array
    {
        return [
            ...$this->promises->countOpenDue($workspaceId, now(config('promises.timezone'))->toDateString()),
            'hot_leads' => $this->conversations->countHotLeads($workspaceId),
            'unread' => $this->conversations->countUnread($workspaceId),
            'needs_ok' => $this->actions->countByStatus($workspaceId, ActionStatus::Proposed),
            'escalated' => $this->actions->countByStatus($workspaceId, ActionStatus::Escalated),
        ];
    }
}
