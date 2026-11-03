<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Conversation;
use App\Models\Message;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;

/**
 * Sends one approved WhatsApp template into a chat and saves it in the Inbox with the blanks filled in.
 * Used by a staff member sending from the Inbox and by a campaign sending to many people.
 */
class TemplateMessageService
{
    public function __construct(
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private ChannelService $channelService,
        private WhatsAppTemplateService $templateService,
        private OutboundMessageService $outbound,
    ) {}

    /**
     * $template = ['body' => ..., 'blanks' => n] when the caller already checked it with Meta (a campaign checks
     * once, not for every person); null = check it now.
     */
    public function send(Conversation $conversation, ?int $userId, string $name, string $language, array $parameters, ?array $template = null): Message
    {
        $this->ensureAllowedNumber($conversation->customer_phone);

        $template ??= $this->templateService->approved($conversation->workspace_id, $conversation->channel_id, $name, $language);
        $parameters = array_values($parameters);

        if (count($parameters) !== $template['blanks']) {
            throw new BusinessRuleException(__('templates.params_mismatch', ['count' => $template['blanks']]));
        }

        $message = $this->messages->create([
            'conversation_id' => $conversation->id,
            'workspace_id' => $conversation->workspace_id,
            'direction' => 'outbound',
            'type' => 'template',
            'body' => $this->fillBlanks($template['body'], $parameters),
            'status' => 'sent',
            'sent_by_user_id' => $userId,
            'payload' => ['template' => $name, 'language' => $language, 'parameters' => $parameters],
        ]);

        $this->outbound->deliver($message, fn () => $this->channelService->sendTemplate(
            $conversation->workspace_id, $conversation->channel_id, $conversation->customer_phone, $name, $language, $parameters,
        ));

        $this->conversations->touchLastMessage($conversation);

        return $message->refresh();
    }

    // While testing, only the numbers in CAMPAIGN_ONLY_SEND_TO may get a message (empty list = anyone)
    private function ensureAllowedNumber(string $phone): void
    {
        $allowed = config('campaigns.only_send_to');

        if ($allowed !== [] && ! in_array($phone, $allowed, true)) {
            throw new BusinessRuleException(__('campaigns.test_numbers_only'), 403);
        }
    }

    // "Namaste {{1}}, {{2}} baaki hai" + ['Ramesh', '₹1,200'] -> "Namaste Ramesh, ₹1,200 baaki hai"
    public function fillBlanks(string $body, array $parameters): string
    {
        return preg_replace_callback('/\{\{(\d+)\}\}/', fn ($m) => $parameters[(int) $m[1] - 1] ?? $m[0], $body);
    }
}
