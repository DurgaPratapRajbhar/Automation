<?php

namespace App\Services;

use App\Enums\PromiseSource;
use App\Enums\PromiseStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Promise;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\PromiseRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

// The Promises page: what customers said they will do by a date, and what the owner did about it
class PromiseService
{
    public function __construct(
        private PromiseRepositoryInterface $promises,
        private ConversationRepositoryInterface $conversations,
    ) {}

    // $status null = every status
    public function list(int $workspaceId, ?PromiseStatus $status, int $perPage): CursorPaginator
    {
        $perPage = max(1, min($perPage, config('pagination.max_per_page')));

        return $this->promises
            ->cursorForWorkspace($workspaceId, $status, $perPage)
            ->through(fn (Promise $promise) => $this->present($promise));
    }

    public function create(int $workspaceId, array $data): array
    {
        $conversation = isset($data['conversation_id'])
            ? ($this->conversations->findInWorkspace($workspaceId, $data['conversation_id']) ?? throw new BusinessRuleException(__('conversations.not_found'), 404))
            : null;

        return $this->present($this->promises->create([
            'workspace_id' => $workspaceId,
            'conversation_id' => $conversation?->id,
            'customer_name' => $conversation?->customer_name ?? $data['customer_name'] ?? null,
            'customer_phone' => $conversation?->customer_phone ?? $data['customer_phone'] ?? null,
            'kind' => $data['kind'],
            'quote' => $data['quote'],
            'amount' => $data['amount'] ?? null,
            'due_on' => $data['due_on'],
            'source' => PromiseSource::Manual->value,
        ]));
    }

    // Done / not coming / open again
    public function setStatus(int $workspaceId, int $promiseId, PromiseStatus $status): array
    {
        $promise = $this->promises->findInWorkspace($workspaceId, $promiseId)
            ?? throw new BusinessRuleException(__('promises.not_found'), 404);

        $this->promises->setStatus($promise, $status);

        return $this->present($promise->refresh());
    }

    private function present(Promise $promise): array
    {
        return [
            'id' => $promise->id,
            'conversation_id' => $promise->conversation_id,
            'customer_name' => $promise->customer_name,
            'customer_phone' => $promise->customer_phone,
            'kind' => $promise->kind->value,
            'quote' => $promise->quote,
            'amount' => $promise->amount,
            'due_on' => $promise->due_on->toDateString(),
            'status' => $promise->status->value,
            'source' => $promise->source->value,
            // overdue / today / later: what the page groups by (for an open promise)
            'timing' => $this->timing($promise),
        ];
    }

    private function timing(Promise $promise): ?string
    {
        if ($promise->status !== PromiseStatus::Open) {
            return null;
        }

        $today = now(config('promises.timezone'))->toDateString();
        $due = $promise->due_on->toDateString();

        return $due < $today ? 'overdue' : ($due === $today ? 'today' : 'later');
    }
}
