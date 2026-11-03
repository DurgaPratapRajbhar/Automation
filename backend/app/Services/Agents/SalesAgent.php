<?php

namespace App\Services\Agents;

use App\Enums\LeadStatus;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Agents\Sales\QualifierInterface;
use App\Services\AiUsageService;
use App\Services\OwnerNotifier;

// Talks to a new person: asks the owner's questions one at a time, then says how ready the person is.
class SalesAgent implements AgentInterface
{
    private const HISTORY = 20;

    public function __construct(
        private WorkspaceRepositoryInterface $workspaces,
        private MessageRepositoryInterface $messages,
        private ConversationRepositoryInterface $conversations,
        private QualifierInterface $qualifier,
        private BotMessageService $bot,
        private AiUsageService $usage,
        private ChatTranscript $transcript,
        private OwnerNotifier $owner,
    ) {}

    // A person's first message starts the flow; it stays this agent's job until the questions are answered
    public function handles(Conversation $conversation, Message $message): bool
    {
        if (! $this->questions($conversation)) {
            return false;
        }

        return $conversation->lead_status === LeadStatus::Qualifying->value
            || ($conversation->lead_status === null && ! $this->messages->hasEarlierInbound($conversation->id, $message->id));
    }

    public function reply(Conversation $conversation, Message $message): void
    {
        // Same rule as the support agent: a trial that is over or a used-up day pays for nothing
        if (! $this->usage->allowed($conversation->workspace_id)) {
            return;
        }

        $questions = $this->questions($conversation);
        $result = $this->qualifier->qualify($questions, $this->transcript->of($conversation->id, self::HISTORY));

        if (! $result) {
            return;
        }

        $this->bot->send($conversation, $result['reply']);

        $status = $result['done'] ? LeadStatus::fromScore($result['score']) : LeadStatus::Qualifying;
        $becameHot = $status === LeadStatus::Hot && $conversation->lead_status !== LeadStatus::Hot->value;

        $this->conversations->setLead(
            $conversation,
            $status->value,
            $this->answered($questions, $result['answers']),
            $result['summary'] ?: null,
        );

        if ($becameHot) {
            $this->owner->notify($conversation->workspace_id, __('alerts.hot_lead', [
                'customer' => $conversation->customer_name ?? '+'.$conversation->customer_phone,
                'summary' => $result['summary'],
            ]));
        }
    }

    /** @return string[] the owner's questions; empty when the flow is off */
    private function questions(Conversation $conversation): array
    {
        $workspace = $this->workspaces->findById($conversation->workspace_id);

        return $workspace?->lead_flow_enabled ? array_values(array_filter($workspace->lead_questions ?? [])) : [];
    }

    /** @return array<int, array{question: string, answer: string|null}> */
    private function answered(array $questions, array $answers): array
    {
        return collect($questions)
            ->map(fn (string $question, int $i) => ['question' => $question, 'answer' => $answers[$i] ?? null])
            ->all();
    }
}
