<?php

namespace App\Services\Agents;

use App\Enums\AgentName;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\ChannelService;
use App\Services\OutboundMessageService;

// What every agent does to answer: save the message in the Inbox (sent by the bot), send it on WhatsApp
class BotMessageService
{
    public function __construct(
        private MessageRepositoryInterface $messages,
        private ConversationRepositoryInterface $conversations,
        private ChannelService $channelService,
        private OutboundMessageService $outbound,
    ) {}

    // $agent = who is talking, so the next customer message can stay with the same agent
    public function send(Conversation $conversation, string $text, ?AgentName $agent = null): Message
    {
        // sent_by_user_id null = bot
        $message = $this->messages->create([
            'conversation_id' => $conversation->id,
            'workspace_id' => $conversation->workspace_id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => $text,
            'status' => 'sent',
            'payload' => ['ai' => true] + ($agent ? ['agent' => $agent->value] : []),
        ]);

        // A failed WhatsApp send is kept with the reason
        $this->outbound->deliver($message, fn () => $this->channelService->sendMessage(
            $conversation->workspace_id,
            $conversation->channel_id,
            $conversation->customer_phone,
            $text,
        ));

        $this->conversations->touchLastMessage($conversation);

        return $message->refresh();
    }
}
