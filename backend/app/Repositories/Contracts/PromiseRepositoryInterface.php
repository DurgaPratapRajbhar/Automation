<?php

namespace App\Repositories\Contracts;

use App\Enums\PromiseStatus;
use App\Models\Promise;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

interface PromiseRepositoryInterface
{
    public function create(array $data): Promise;

    // Earliest date first, so what is late comes first. $status null = every status
    public function cursorForWorkspace(int $workspaceId, ?PromiseStatus $status, int $perPage): CursorPaginator;

    public function findInWorkspace(int $workspaceId, int $promiseId): ?Promise;

    public function setStatus(Promise $promise, PromiseStatus $status): void;

    // Open promises already late (before $today) and due on $today (YYYY-MM-DD)
    public function countOpenDue(int $workspaceId, string $today): array;

    // Open payment promises of a chat that are already late (before $today, YYYY-MM-DD)
    public function overduePayments(int $workspaceId, string $today): Collection;

    public function find(int $promiseId): ?Promise;

    // The same open promise (same chat, kind and date) is already saved
    public function openExists(int $conversationId, string $kind, string $dueOn): bool;
}
