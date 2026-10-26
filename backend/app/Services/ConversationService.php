<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

// Inbox: dukaan ki saari WhatsApp chats, customer + bot + staff ke messages, aur staff ka reply
class ConversationService
{
    // Chat screen par itne purane messages tak dikhate hain
    private const MESSAGE_LIMIT = 200;

    // WhatsApp allows free text only this long after the customer's last message
    private const REPLY_WINDOW_HOURS = 24;

    public function __construct(
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private ChannelService $channelService,
        private WhatsAppTemplateService $templateService,
        private OutboundMessageService $outbound,
    ) {}

    public function list(int $workspaceId, ?string $search, int $perPage): CursorPaginator
    {
        $perPage = max(1, min($perPage, config('pagination.max_per_page')));

        return $this->conversations
            ->cursorForWorkspace($workspaceId, $search, $perPage)
            ->through(fn (Conversation $conversation) => $this->presentConversation($conversation));
    }

    // Chat kholna = padh liya, isliye unread zero
    public function show(int $workspaceId, int $conversationId): array
    {
        $conversation = $this->findOrFail($workspaceId, $conversationId);

        $this->conversations->markRead($conversation);

        $lastInbound = $this->messages->lastInboundAt($conversation->id);

        return [
            ...$this->presentConversation($conversation->refresh()),
            // false = only an approved template can be sent now
            'window_open' => $lastInbound !== null && $lastInbound->gt(now()->subHours(self::REPLY_WINDOW_HOURS)),
            'messages' => $this->messages
                ->forConversation($conversation->id, self::MESSAGE_LIMIT)
                ->map(fn (Message $message) => $this->presentMessage($message))
                ->all(),
        ];
    }

    // Staff ka reply: pehle inbox me save, phir WhatsApp; fail hua to status failed + wajah
    public function reply(int $workspaceId, int $conversationId, int $userId, string $body): array
    {
        $conversation = $this->findOrFail($workspaceId, $conversationId);

        $message = $this->messages->create([
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspaceId,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => $body,
            'status' => 'sent',
            'sent_by_user_id' => $userId,
        ]);

        $this->outbound->deliver($message, fn () => $this->channelService->sendMessage(
            $workspaceId, $conversation->channel_id, $conversation->customer_phone, $body,
        ));

        $this->conversations->touchLastMessage($conversation);

        return $this->presentMessage($message->refresh()->load('sentBy:id,name'));
    }

    // Approved template with the shop's values; saved in the inbox with the blanks filled in
    public function sendTemplate(int $workspaceId, int $conversationId, int $userId, string $name, string $language, array $parameters): array
    {
        $conversation = $this->findOrFail($workspaceId, $conversationId);

        $template = $this->templateService->approved($workspaceId, $conversation->channel_id, $name, $language);

        $parameters = array_values($parameters);

        if (count($parameters) !== $template['blanks']) {
            throw new BusinessRuleException(__('templates.params_mismatch', ['count' => $template['blanks']]));
        }

        $body = preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => $parameters[(int) $m[1] - 1] ?? $m[0], $template['body']);

        $message = $this->messages->create([
            'conversation_id' => $conversation->id,
            'workspace_id' => $workspaceId,
            'direction' => 'outbound',
            'type' => 'template',
            'body' => $body,
            'status' => 'sent',
            'sent_by_user_id' => $userId,
            'payload' => ['template' => $name, 'language' => $language, 'parameters' => $parameters],
        ]);

        $this->outbound->deliver($message, fn () => $this->channelService->sendTemplate(
            $workspaceId, $conversation->channel_id, $conversation->customer_phone, $name, $language, $parameters,
        ));

        $this->conversations->touchLastMessage($conversation);

        return $this->presentMessage($message->refresh()->load('sentBy:id,name'));
    }

    private function findOrFail(int $workspaceId, int $conversationId): Conversation
    {
        $conversation = $this->conversations->findInWorkspace($workspaceId, $conversationId);

        if (! $conversation) {
            throw new BusinessRuleException(__('conversations.not_found'), 404);
        }

        return $conversation;
    }

    private function presentConversation(Conversation $conversation): array
    {
        $last = $conversation->latestMessage;

        return [
            'id' => $conversation->id,
            'channel_id' => $conversation->channel_id,
            'customer_name' => $conversation->customer_name,
            'customer_phone' => $conversation->customer_phone,
            'status' => $conversation->status,
            'unread_count' => $conversation->unread_count,
            'last_message_at' => $conversation->last_message_at,
            'last_message' => $last ? [
                'body' => $last->body,
                'direction' => $last->direction,
                'is_ai' => $this->isAi($last),
            ] : null,
        ];
    }

    private function presentMessage(Message $message): array
    {
        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'type' => $message->type,
            'body' => $message->body,
            'status' => $message->status,
            'error' => $message->error,
            'is_ai' => $this->isAi($message),
            'sent_by' => $message->sentBy?->name,
            'created_at' => $message->created_at,
        ];
    }

    // Bot ka message = outbound jo kisi staff ne nahi bheja
    private function isAi(Message $message): bool
    {
        return $message->direction === 'outbound' && $message->sent_by_user_id === null;
    }
}
