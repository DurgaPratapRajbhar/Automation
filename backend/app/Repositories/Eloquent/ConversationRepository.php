<?php

namespace App\Repositories\Eloquent;

use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;

class ConversationRepository implements ConversationRepositoryInterface
{
    public function findOrCreateForCustomer(
        int $workspaceId,
        int $channelId,
        string $customerPhone,
        ?string $customerName
    ): Conversation {
        return Conversation::firstOrCreate(
            // 1st array: how to FIND it
            ['channel_id' => $channelId, 'customer_phone' => $customerPhone],
            // 2nd array: extra values used only when CREATING
            ['workspace_id' => $workspaceId, 'customer_name' => $customerName]
        );
    }

    public function touchLastMessage(Conversation $conversation): void
    {
        $conversation->update([
            'last_message_at' => now(),
            'status' => 'open',   // closed chat reopens when customer writes again
        ]);
    }
}
