<?php

namespace App\Services\Actions;

use App\Enums\ActionStatus;
use App\Enums\ActionType;
use App\Enums\AgentName;
use App\Exceptions\BusinessRuleException;
use App\Models\AgentAction;
use App\Repositories\Contracts\ActionRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Throwable;

/**
 * The Verified Actions layer. An agent proposes; the owner approves; the handler executes; the result is checked;
 * everything is written to the audit log; anything that fails or cannot be checked is escalated to a person.
 */
class ActionService
{
    /** @param iterable<ActionHandlerInterface> $handlers */
    public function __construct(
        private ActionRepositoryInterface $actions,
        private AuditLogRepositoryInterface $auditLogs,
        private iterable $handlers = [],
    ) {}

    public function propose(int $workspaceId, AgentName $agent, ActionType $type, string $summary, array $payload, ?int $conversationId = null): AgentAction
    {
        $action = $this->actions->create([
            'workspace_id' => $workspaceId,
            'conversation_id' => $conversationId,
            'agent' => $agent->value,
            'type' => $type->value,
            'summary' => mb_substr($summary, 0, 500),
            'payload' => $payload,
        ]);

        $this->audit($action, 'proposed', null);

        return $action;
    }

    // $status null = every status
    public function list(int $workspaceId, ?ActionStatus $status, int $perPage): CursorPaginator
    {
        $perPage = max(1, min($perPage, config('pagination.max_per_page')));

        return $this->actions
            ->cursorForWorkspace($workspaceId, $status, $perPage)
            ->through(fn (AgentAction $action) => $this->present($action));
    }

    // $userId null = the owner's standing yes (a switch in the settings), not a click
    public function approve(int $workspaceId, int $actionId, ?int $userId): array
    {
        $action = $this->pending($workspaceId, $actionId);
        $this->claim($action, $userId);

        try {
            $handler = $this->handlerFor($action->type);
            $result = $handler->execute($action);
            $verified = $handler->verify($action, $result);

            $this->actions->update($action, $verified
                ? ['status' => ActionStatus::Executed->value, 'result' => $result]
                : ['status' => ActionStatus::Escalated->value, 'result' => $result, 'error' => __('actions.not_verified')]);
        } catch (Throwable $e) {
            report($e);
            $this->actions->update($action, ['status' => ActionStatus::Escalated->value, 'error' => mb_substr($e->getMessage(), 0, 500)]);
        }

        $this->audit($action->refresh(), $action->status->value, $userId);

        return $this->present($action);
    }

    // No to a question, or dismissing a problem (an escalated action): what the agent prepared is undone
    public function reject(int $workspaceId, int $actionId, int $userId): array
    {
        $action = $this->actions->findInWorkspace($workspaceId, $actionId) ?? throw new BusinessRuleException(__('actions.not_found'), 404);

        if ($action->status === ActionStatus::Escalated) {
            $this->audit($action, 'dismissed', $userId);
        } else {
            $this->pending($workspaceId, $actionId);
            $this->claim($action, $userId);
        }

        $this->findHandler($action->type)?->rejected($action);
        $this->actions->update($action, ['status' => ActionStatus::Rejected->value]);
        $this->audit($action->refresh(), 'rejected', $userId);

        return $this->present($action);
    }

    // Only one request wins the right to decide: a second click or device gets "already decided"
    private function claim(AgentAction $action, ?int $userId): void
    {
        if (! $this->actions->claim($action, $userId)) {
            throw new BusinessRuleException(__('actions.already_decided'), 422);
        }
    }

    private function pending(int $workspaceId, int $actionId): AgentAction
    {
        $action = $this->actions->findInWorkspace($workspaceId, $actionId)
            ?? throw new BusinessRuleException(__('actions.not_found'), 404);

        if ($action->status !== ActionStatus::Proposed) {
            throw new BusinessRuleException(__('actions.already_decided'), 422);
        }

        return $action;
    }

    private function handlerFor(ActionType $type): ActionHandlerInterface
    {
        return $this->findHandler($type) ?? throw new BusinessRuleException(__('actions.no_handler'), 422);
    }

    private function findHandler(ActionType $type): ?ActionHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->type() === $type) {
                return $handler;
            }
        }

        return null;
    }

    private function audit(AgentAction $action, string $what, ?int $userId): void
    {
        $this->auditLogs->create([
            'workspace_id' => $action->workspace_id,
            'actor_user_id' => $userId,
            'action' => "agent_action.{$what}",
            'subject_type' => 'agent_action',
            'subject_id' => $action->id,
            'changes' => ['agent' => $action->agent->value, 'type' => $action->type->value, 'summary' => $action->summary],
        ]);
    }

    private function present(AgentAction $action): array
    {
        return [
            'id' => $action->id,
            'conversation_id' => $action->conversation_id,
            'agent' => $action->agent->value,
            'type' => $action->type->value,
            'summary' => $action->summary,
            'status' => $action->status->value,
            'error' => $action->error,
            'created_at' => $action->created_at,
        ];
    }
}
