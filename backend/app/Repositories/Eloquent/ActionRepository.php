<?php

namespace App\Repositories\Eloquent;

use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Models\AgentAction;
use App\Repositories\Contracts\ActionRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

class ActionRepository implements ActionRepositoryInterface
{
    public function create(array $data): AgentAction
    {
        // refreshed: the database fills in the status
        return AgentAction::create($data)->refresh();
    }

    public function findInWorkspace(int $workspaceId, int $actionId): ?AgentAction
    {
        return AgentAction::where('workspace_id', $workspaceId)->find($actionId);
    }

    public function cursorForWorkspace(int $workspaceId, ?ActionStatus $status, int $perPage): CursorPaginator
    {
        return AgentAction::where('workspace_id', $workspaceId)
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function update(AgentAction $action, array $changes): void
    {
        $action->update($changes);
    }

    public function claim(AgentAction $action, ?int $userId): bool
    {
        return AgentAction::where('id', $action->id)
            ->where('status', ActionStatus::Proposed->value)
            ->whereNull('decided_at')
            ->update(['decided_by' => $userId, 'decided_at' => now()]) === 1;
    }

    public function rejectPending(int $workspaceId, ActionType $type, string $key, int $value): void
    {
        AgentAction::where('workspace_id', $workspaceId)
            ->where('type', $type->value)
            ->where('status', ActionStatus::Proposed->value)
            ->where("payload->{$key}", $value)
            ->update(['status' => ActionStatus::Rejected->value, 'decided_at' => now()]);
    }

    public function existsRecent(int $workspaceId, ActionType $type, string $key, int|string $value, CarbonInterface $since): bool
    {
        return AgentAction::where('workspace_id', $workspaceId)
            ->where('type', $type->value)
            ->where("payload->{$key}", $value)
            ->where('created_at', '>=', $since)
            ->exists();
    }

    public function countByStatus(int $workspaceId, ActionStatus $status): int
    {
        return AgentAction::where('workspace_id', $workspaceId)->where('status', $status->value)->count();
    }
}
