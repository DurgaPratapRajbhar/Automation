<?php

namespace App\Services\Actions;

use App\Enums\ActionType;
use App\Models\AgentAction;
use App\Services\OwnerNotifier;
use App\Services\ReportService;

// The owner said yes to the daily report: send it to the owner's own WhatsApp number
class ReportSendHandler implements ActionHandlerInterface
{
    public function __construct(
        private OwnerNotifier $owner,
        private ReportService $reports,
    ) {}

    public function type(): ActionType
    {
        return ActionType::ReportSend;
    }

    public function execute(AgentAction $action): array
    {
        $response = $this->owner->send($action->workspace_id, $this->reports->build($action->workspace_id));

        return ['to' => $this->owner->phone($action->workspace_id), 'wamid' => $response['messages'][0]['id'] ?? null];
    }

    public function rejected(AgentAction $action): void {}

    // WhatsApp gave the message an id: it was accepted
    public function verify(AgentAction $action, array $result): bool
    {
        return ! empty($result['wamid']);
    }
}
