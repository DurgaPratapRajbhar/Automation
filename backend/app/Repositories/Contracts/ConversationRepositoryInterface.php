<?php

namespace App\Repositories\Contracts;

use App\Models\Conversation;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface ConversationRepositoryInterface
{
    public function findOrCreateForCustomer(
        int $workspaceId,
        int $channelId,
        string $customerPhone,
        ?string $customerName
    ): Conversation;

    public function find(int $id): ?Conversation;

    // Inbox: latest chat first, search by customer name or phone
    public function cursorForWorkspace(int $workspaceId, ?string $search, int $perPage): CursorPaginator;

    public function findInWorkspace(int $workspaceId, int $conversationId): ?Conversation;

    public function incrementUnread(Conversation $conversation): void;

    public function markRead(Conversation $conversation): void;

    public function setCustomerLanguage(Conversation $conversation, string $language): void;

    // What the Sales agent found out about this person
    public function setLead(Conversation $conversation, string $status, array $answers, ?string $summary): void;

    // Chats the Sales agent scored hot, and chats with messages nobody has read
    public function countHotLeads(int $workspaceId): int;

    public function countUnread(int $workspaceId): int;

    public function touchLastMessage(Conversation $conversation): void;

    // The bot stays quiet in this chat until then
    public function pauseBot(Conversation $conversation, \DateTimeInterface $until): void;
}
