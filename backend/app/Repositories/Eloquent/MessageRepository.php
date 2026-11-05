<?php

namespace App\Repositories\Eloquent;

use App\Enums\MessageStatus;
use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class MessageRepository implements MessageRepositoryInterface
{
    public function find(int $id): ?Message
    {
        return Message::find($id);
    }

    public function forConversation(int $conversationId, int $limit): Collection
    {
        return Message::with('sentBy:id,name')
            ->where('conversation_id', $conversationId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function before(int $conversationId, int $messageId, int $limit): Collection
    {
        return Message::where('conversation_id', $conversationId)
            ->where('id', '<', $messageId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function create(array $data): Message
    {
        return Message::create($data);
    }

    public function hasEarlierInbound(int $conversationId, int $messageId): bool
    {
        return Message::where('conversation_id', $conversationId)->where('direction', 'inbound')->where('id', '<', $messageId)->exists();
    }

    public function lastInboundAt(int $conversationId): ?CarbonInterface
    {
        return Message::where('conversation_id', $conversationId)
            ->where('direction', 'inbound')
            ->latest('id')
            ->first()
            ?->created_at;
    }

    public function setStatus(Message $message, string $status): void
    {
        $message->update(['status' => $status]);
    }

    public function findInConversation(int $workspaceId, int $conversationId, int $messageId): ?Message
    {
        return Message::where('workspace_id', $workspaceId)->where('conversation_id', $conversationId)->find($messageId);
    }

    public function setTranslation(Message $message, ?string $language, ?string $translation): void
    {
        $message->update(['language' => $language, 'translation' => $translation]);
    }

    public function markFailed(Message $message, string $error): void
    {
        $message->update(['status' => MessageStatus::Failed->value, 'error' => $error]);
    }

    public function setExternalId(Message $message, string $externalId): void
    {
        $message->update(['external_id' => $externalId]);
    }

    public function existsByExternalId(string $externalId): bool
    {
        return Message::where('external_id', $externalId)->exists();
    }

    public function findByExternalId(string $externalId): ?Message
    {
        return Message::where('external_id', $externalId)->first();
    }
}
