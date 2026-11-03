<?php

namespace App\Services\Actions;

use App\Enums\ActionType;
use App\Models\AgentAction;

// Does one kind of action. Throw to say it failed.
interface ActionHandlerInterface
{
    public function type(): ActionType;

    // Do it. Returns what happened (saved on the action).
    public function execute(AgentAction $action): array;

    // The owner said no (or dismissed a problem): undo what the agent prepared
    public function rejected(AgentAction $action): void;

    // Check that it really happened, from what execute() returned
    public function verify(AgentAction $action, array $result): bool;
}
