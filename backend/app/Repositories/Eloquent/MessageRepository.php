<?php

namespace App\Repositories\Eloquent;

use App\Models\Message;
use App\Repositories\Contracts\MessageRepositoryInterface;
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

    public function create(array $data): Message
    {
        return Message::create($data);
    }

    public function setStatus(Message $message, string $status): void
    {
        $message->update(['status' => $status]);
    }

    public function existsByExternalId(string $externalId): bool
    {
        return Message::where('external_id', $externalId)->exists();
    }
}
