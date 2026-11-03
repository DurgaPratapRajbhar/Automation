<?php

namespace App\Repositories\Contracts;

use App\Models\Message;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface MessageRepositoryInterface
{
    public function find(int $id): ?Message;

    // A message of this chat, only if the chat belongs to the workspace
    public function findInConversation(int $workspaceId, int $conversationId, int $messageId): ?Message;

    // Chat screen: last $limit messages, oldest first
    public function forConversation(int $conversationId, int $limit): Collection;

    // When the customer last wrote (starts WhatsApp's 24-hour reply window)
    // The customer wrote before this message in the same chat
    public function hasEarlierInbound(int $conversationId, int $messageId): bool;

    public function lastInboundAt(int $conversationId): ?CarbonInterface;

    public function create(array $data): Message;

    public function setStatus(Message $message, string $status): void;

    // language = the language of the text; translation = the same text in the business's Inbox language (null = none)
    public function setTranslation(Message $message, ?string $language, ?string $translation): void;

    public function markFailed(Message $message, string $error): void;

    // Meta's wamid of a message we sent
    public function setExternalId(Message $message, string $externalId): void;

    public function existsByExternalId(string $externalId): bool;

    public function findByExternalId(string $externalId): ?Message;
}
