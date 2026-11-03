<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\Agents\BotMessageService;

// Free text to a customer is allowed by WhatsApp only for a day after their last message
class CustomerReplyService
{
    private const WINDOW_HOURS = 24;

    public function __construct(
        private MessageRepositoryInterface $messages,
        private BotMessageService $bot,
    ) {}

    // Throws when the day is over: only an approved template can be sent then
    public function sendWithinWindow(Conversation $conversation, string $text): Message
    {
        $lastInbound = $this->messages->lastInboundAt($conversation->id);

        if (! $lastInbound || $lastInbound->lt(now()->subHours(self::WINDOW_HOURS))) {
            throw new BusinessRuleException(__('actions.window_closed'), 422);
        }

        return $this->bot->send($conversation, $text);
    }
}
