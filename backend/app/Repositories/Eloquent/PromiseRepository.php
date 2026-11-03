<?php

namespace App\Repositories\Eloquent;

use App\Enums\PromiseKind;
use App\Enums\PromiseStatus;
use App\Models\Promise;
use App\Repositories\Contracts\PromiseRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

class PromiseRepository implements PromiseRepositoryInterface
{
    public function create(array $data): Promise
    {
        // refreshed: the database fills in the status
        return Promise::create($data)->refresh();
    }

    public function cursorForWorkspace(int $workspaceId, ?PromiseStatus $status, int $perPage): CursorPaginator
    {
        return Promise::where('workspace_id', $workspaceId)
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->orderBy('due_on')
            ->orderBy('id')
            ->cursorPaginate($perPage);
    }

    public function findInWorkspace(int $workspaceId, int $promiseId): ?Promise
    {
        return Promise::where('workspace_id', $workspaceId)->find($promiseId);
    }

    public function setStatus(Promise $promise, PromiseStatus $status): void
    {
        $promise->update(['status' => $status->value]);
    }

    public function countOpenDue(int $workspaceId, string $today): array
    {
        $open = Promise::where('workspace_id', $workspaceId)->where('status', PromiseStatus::Open->value);

        return [
            'overdue' => (clone $open)->whereDate('due_on', '<', $today)->count(),
            'today' => (clone $open)->whereDate('due_on', $today)->count(),
        ];
    }

    public function overduePayments(int $workspaceId, string $today): Collection
    {
        return Promise::where('workspace_id', $workspaceId)
            ->where('status', PromiseStatus::Open->value)
            ->where('kind', PromiseKind::Payment->value)
            ->whereNotNull('conversation_id')
            ->whereDate('due_on', '<', $today)
            ->orderBy('due_on')
            ->get();
    }

    public function find(int $promiseId): ?Promise
    {
        return Promise::find($promiseId);
    }

    public function openExists(int $conversationId, string $kind, string $dueOn): bool
    {
        return Promise::where('conversation_id', $conversationId)
            ->where('kind', $kind)
            ->whereDate('due_on', $dueOn)
            ->where('status', PromiseStatus::Open->value)
            ->exists();
    }
}
