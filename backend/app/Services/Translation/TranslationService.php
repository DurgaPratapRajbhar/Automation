<?php

namespace App\Services\Translation;

use App\Exceptions\BusinessRuleException;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\AiUsageService;

/**
 * The language bridge: the business reads and writes in its own language (the Inbox language), the customer in theirs.
 * Messages that come in get a translation; a reply written in the Inbox language can be sent in the customer's.
 */
class TranslationService
{
    public function __construct(
        private TranslatorInterface $translator,
        private MessageRepositoryInterface $messages,
        private ConversationRepositoryInterface $conversations,
        private WorkspaceRepositoryInterface $workspaces,
        private AiUsageService $usage,
    ) {}

    /** The customer's message gets a version in the Inbox language, and the chat remembers the customer's language. */
    public function translateInbound(int $messageId): void
    {
        $message = $this->messages->find($messageId);

        if (! $message || $message->direction !== 'inbound' || ! $message->body || $message->translation) {
            return;
        }

        // Same rule as the AI reply: a trial that is over, a suspended business or a used-up day pays for nothing
        if (! $this->usage->allowed($message->workspace_id)) {
            return;
        }

        $inbox = $this->workspaces->findById($message->workspace_id)->inbox_language;
        $result = $this->translator->translate($message->body, $this->nameOf($inbox));

        if (! $result) {
            return;
        }

        $language = $this->cleanCode($result['language']);

        // Written in the Inbox language already: nothing to show underneath
        $this->messages->setTranslation($message, $language, $language === $inbox ? null : $result['translation']);

        if ($language) {
            $this->conversations->setCustomerLanguage($this->conversations->find($message->conversation_id), $language);
        }
    }

    /**
     * The reply written in the Inbox language, in the customer's language (or $to), to look at before sending.
     *
     * @return array{text: string, to: string, to_name: string}
     */
    public function previewReply(int $workspaceId, int $conversationId, string $text, ?string $to): array
    {
        $conversation = $this->conversations->findInWorkspace($workspaceId, $conversationId)
            ?? throw new BusinessRuleException(__('conversations.not_found'), 404);

        $target = $to ?? $conversation->customer_language;

        if (! $target || ! isset($this->languages()[$target])) {
            throw new BusinessRuleException(__('translation.unknown_language'), 422);
        }

        if (! $this->usage->allowed($workspaceId)) {
            throw new BusinessRuleException(__('translation.not_available'), 403);
        }

        $result = $this->translator->translate($text, $this->languages()[$target])
            ?? throw new BusinessRuleException(__('translation.failed'), 503);

        return ['text' => $result['translation'], 'to' => $target, 'to_name' => $this->languages()[$target]];
    }

    private function languages(): array
    {
        return config('translation.languages');
    }

    private function nameOf(string $code): string
    {
        return $this->languages()[$code] ?? $code;
    }

    // "EN", "en-US" -> "en"; anything that is not a two-letter code -> null
    private function cleanCode(?string $code): ?string
    {
        $code = strtolower(substr(trim((string) $code), 0, 2));

        return preg_match('/^[a-z]{2}$/', $code) ? $code : null;
    }
}
