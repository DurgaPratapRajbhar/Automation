<?php

namespace App\Services\Translation;

use App\Exceptions\BusinessRuleException;
use App\Models\Message;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\AiUsageService;

/**
 * The language bridge: the business reads and writes in its own language (the Inbox language), the customer in theirs.
 * A message is translated into the Inbox language when someone clicks "Translate" on it (never by itself, so nothing is
 * paid for translations nobody reads); a reply can be translated into the customer's language before it is sent.
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

    /**
     * A message gets a version in the Inbox language (kept, so a second click costs nothing), and the chat remembers
     * the language the customer writes in.
     *
     * @return array{id: int, language: string|null, translation: string|null}
     */
    public function translateMessage(int $workspaceId, int $conversationId, int $messageId): array
    {
        $message = $this->messages->findInConversation($workspaceId, $conversationId, $messageId)
            ?? throw new BusinessRuleException(__('conversations.not_found'), 404);

        if (! $message->body) {
            throw new BusinessRuleException(__('translation.no_text'), 422);
        }

        if ($message->language !== null || $message->translation !== null) {
            return $this->present($message); // done before
        }

        // Same rule as the AI reply: a trial that is over, a suspended business or a used-up day pays for nothing
        if (! $this->usage->allowed($workspaceId)) {
            throw new BusinessRuleException(__('translation.not_available'), 403);
        }

        $inbox = $this->workspaces->findById($workspaceId)->inbox_language;
        $result = $this->translator->translate($message->body, $this->nameOf($inbox))
            ?? throw new BusinessRuleException(__('translation.failed'), 503);

        $unchanged = trim($result['translation']) === trim($message->body);
        $language = $this->languageOf($result['language'], $unchanged, $inbox);

        // Written in the Inbox language already: nothing to show underneath
        $this->messages->setTranslation($message, $language, $unchanged ? null : $result['translation']);

        if ($language && $message->direction === 'inbound') {
            $this->conversations->setCustomerLanguage($this->conversations->find($conversationId), $language);
        }

        return $this->present($message->refresh());
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

    private function present(Message $message): array
    {
        return ['id' => $message->id, 'language' => $message->language, 'translation' => $message->translation];
    }

    private function languages(): array
    {
        return config('translation.languages');
    }

    private function nameOf(string $code): string
    {
        return $this->languages()[$code] ?? $code;
    }

    /**
     * The language the customer wrote in. A message that came back unchanged was already in the Inbox language; one that
     * was changed cannot have been (a model sometimes names the language it translated INTO), so that answer is dropped.
     */
    private function languageOf(?string $reported, bool $unchanged, string $inbox): ?string
    {
        if ($unchanged) {
            return $inbox;
        }

        $language = $this->cleanCode($reported);

        return $language === $inbox ? null : $language;
    }

    // "EN", "en-US" -> "en"; anything that is not a two-letter code -> null
    private function cleanCode(?string $code): ?string
    {
        $code = strtolower(substr(trim((string) $code), 0, 2));

        return preg_match('/^[a-z]{2}$/', $code) ? $code : null;
    }
}
