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

    public function touchLastMessage(Conversation $conversation): void;
}
