<?php

namespace App\Repositories\Contracts;

use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Models\AgentAction;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface ActionRepositoryInterface
{
    public function create(array $data): AgentAction;

    public function findInWorkspace(int $workspaceId, int $actionId): ?AgentAction;

    // Newest first. $status null = every status
    public function cursorForWorkspace(int $workspaceId, ?ActionStatus $status, int $perPage): CursorPaginator;

    public function update(AgentAction $action, array $changes): void;

    // The first caller to decide an action gets true; any other (a double click, a second device) gets false
    public function claim(AgentAction $action, ?int $userId): bool;

    // Close the questions of this type that are still waiting and point to this id (payload[$key] = $value)
    public function rejectPending(int $workspaceId, ActionType $type, string $key, int $value): void;

    // An action of this type with payload[$key] = $value was proposed since $since (so an agent does not ask twice)
    public function existsRecent(int $workspaceId, ActionType $type, string $key, int|string $value, CarbonInterface $since): bool;

    public function countByStatus(int $workspaceId, ActionStatus $status): int;
}
