<?php

namespace App\Repositories\Contracts;

use App\Models\Conversation;

interface ConversationRepositoryInterface
{
    public function findOrCreateForCustomer(
        int $workspaceId,
        int $channelId,
        string $customerPhone,
        ?string $customerName
    ): Conversation;

    public function find(int $id): ?Conversation;

    public function touchLastMessage(Conversation $conversation): void;
}
