<?php

namespace App\Repositories\Eloquent;

use App\Enums\LeadStatus;
use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;

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

    public function find(int $id): ?Conversation
    {
        return Conversation::find($id);
    }

    public function cursorForWorkspace(int $workspaceId, ?string $search, int $perPage): CursorPaginator
    {
        return Conversation::query()
            ->with('latestMessage')
            ->where('workspace_id', $workspaceId)
            ->when($search !== null && $search !== '', function ($query) use ($search) {
                $like = '%'.mb_strtolower($search).'%';

                $query->where(function ($inner) use ($like) {
                    $inner->whereRaw('LOWER(customer_name) LIKE ?', [$like])
                        ->orWhere('customer_phone', 'like', $like);
                });
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }

    public function findInWorkspace(int $workspaceId, int $conversationId): ?Conversation
    {
        return Conversation::where('workspace_id', $workspaceId)->find($conversationId);
    }

    public function incrementUnread(Conversation $conversation): void
    {
        $conversation->increment('unread_count');
    }

    public function markRead(Conversation $conversation): void
    {
        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }
    }

    public function setCustomerLanguage(Conversation $conversation, string $language): void
    {
        $conversation->update(['customer_language' => $language]);
    }

    public function setLead(Conversation $conversation, string $status, array $answers, ?string $summary): void
    {
        $conversation->update(['lead_status' => $status, 'lead_answers' => $answers, 'lead_summary' => $summary]);
    }

    public function countHotLeads(int $workspaceId): int
    {
        return Conversation::where('workspace_id', $workspaceId)->where('lead_status', LeadStatus::Hot->value)->count();
    }

    public function countUnread(int $workspaceId): int
    {
        return Conversation::where('workspace_id', $workspaceId)->where('unread_count', '>', 0)->count();
    }

    public function touchLastMessage(Conversation $conversation): void
    {
        $conversation->update([
            'last_message_at' => now(),
            'status' => 'open',   // closed chat reopens when customer writes again
        ]);
    }

    public function pauseBot(Conversation $conversation, \DateTimeInterface $until): void
    {
        $conversation->update(['bot_paused_until' => $until]);
    }
}
