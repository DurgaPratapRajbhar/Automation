<?php

namespace App\Services\Agents;

use App\Enums\ActionType;
use App\Enums\AgentName;
use App\Enums\PromiseSource;
use App\Models\Promise;
use App\Repositories\Contracts\ActionRepositoryInterface;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\PromiseRepositoryInterface;
use App\Services\Actions\ActionService;
use App\Services\Agents\Finance\PromiseDetectorInterface;
use App\Services\AiUsageService;
use Illuminate\Support\Str;

// Reads what a customer wrote and saves the promises that have a date ("kal payment kar dunga").
// It only watches: it never sends anything, so it is not in the reply router.
class FinanceAgent
{
    public function __construct(
        private MessageRepositoryInterface $messages,
        private ConversationRepositoryInterface $conversations,
        private PromiseRepositoryInterface $promises,
        private PromiseDetectorInterface $detector,
        private AiUsageService $usage,
        private ChatTranscript $transcript,
        private ActionService $actions,
        private ActionRepositoryInterface $actionRepository,
    ) {}

    public function observe(int $messageId): void
    {
        $message = $this->messages->find($messageId);

        // Only the customer's text, and only text that could hold a dated promise (the AI costs money)
        if (! $message || $message->direction !== 'inbound' || ! $message->body || ! $this->couldBeAPromise($message->body)) {
            return;
        }

        if (! $this->usage->allowed($message->workspace_id, counts: false)) {
            return;
        }

        $conversation = $this->conversations->find($message->conversation_id);

        if (! $conversation) {
            return;
        }

        $today = now(config('promises.timezone'))->toDateString();
        $found = $this->detector->detect($this->transcript->of($conversation->id, config('promises.history')), $today);

        foreach ($found ?? [] as $promise) {
            // A promise made now is for today or later; an earlier date is the model's mistake
            if ($promise['due_on'] < $today || $this->promises->openExists($conversation->id, $promise['kind'], $promise['due_on'])) {
                continue;
            }

            $this->promises->create([
                'workspace_id' => $conversation->workspace_id,
                'conversation_id' => $conversation->id,
                'customer_name' => $conversation->customer_name,
                'customer_phone' => $conversation->customer_phone,
                'kind' => $promise['kind'],
                'quote' => $promise['quote'],
                'amount' => $promise['amount'],
                'due_on' => $promise['due_on'],
                'source' => PromiseSource::Agent->value,
            ]);
        }
    }

    /**
     * Asks the owner to send a reminder for every payment that is late. Not again for the same promise within the
     * quiet days, so the owner is not asked every morning. Returns how many were asked.
     */
    public function proposeReminders(int $workspaceId): int
    {
        $asked = 0;

        foreach ($this->promises->overduePayments($workspaceId, now(config('promises.timezone'))->toDateString()) as $promise) {
            if ($this->actionRepository->existsRecent($workspaceId, ActionType::ReminderSend, 'promise_id', $promise->id, now()->subDays(config('promises.reminder_quiet_days')))) {
                continue;
            }

            $this->actions->propose($workspaceId, AgentName::Finance, ActionType::ReminderSend, $this->summary($promise), ['promise_id' => $promise->id], $promise->conversation_id);
            $asked++;
        }

        return $asked;
    }

    private function summary(Promise $promise): string
    {
        return __('promises.reminder_summary', [
            'name' => $promise->customer_name ?? '+'.$promise->customer_phone,
            'amount' => $promise->amount ? ' of ₹'.number_format((float) $promise->amount, 2) : '',
            'date' => $promise->due_on->format('d M'),
        ]);
    }

    // A digit or one of the hint words
    private function couldBeAPromise(string $text): bool
    {
        if (preg_match('/\d/', $text)) {
            return true;
        }

        return count(array_intersect(preg_split('/[^\p{L}\p{M}]+/u', Str::lower($text), -1, PREG_SPLIT_NO_EMPTY), config('promises.hints'))) > 0;
    }
}
