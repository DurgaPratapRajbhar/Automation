<?php

namespace App\Repositories\Eloquent;

use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChannelRepository implements ChannelRepositoryInterface
{
    public function getByWorkspaceId(int $workspaceId): Collection
    {
        return Channel::with('credential')
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->get();
    }

    public function findInWorkspace(int $workspaceId, int $channelId): ?Channel
    {
        return Channel::with('credential')
            ->where('workspace_id', $workspaceId)
            ->find($channelId);
    }

    public function findConnectedInWorkspace(int $workspaceId, int $channelId): ?Channel
    {
        return Channel::with('credential')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'connected')
            ->find($channelId);
    }

    public function create(array $channelData, array $credentialData): Channel
    {
        return DB::transaction(function () use ($channelData, $credentialData) {
            $channel = Channel::create($channelData);

            $channel->credential()->create($credentialData);

            return $channel->load('credential');
        });
    }

    public function update(Channel $channel, array $channelData, array $credentialData): Channel
    {
        return DB::transaction(function () use ($channel, $channelData, $credentialData) {
            if ($channelData !== []) {
                $channel->update($channelData);
            }

            if ($credentialData !== []) {
                $channel->credential()->updateOrCreate([], $credentialData);
            }

            return $channel->load('credential');
        });
    }

    public function delete(Channel $channel): void
    {
        // channel_credentials cascadeOnDelete se apne aap hat jaata hai
        $channel->delete();
    }

    public function phoneNumberIdExists(string $phoneNumberId, ?int $ignoreChannelId = null): bool
    {
        return Channel::where('phone_number_id', $phoneNumberId)
            ->when($ignoreChannelId, fn ($query) => $query->where('id', '!=', $ignoreChannelId))
            ->exists();
    }

    public function findConnectedByPhoneNumberId(string $phoneNumberId): ?Channel
    {
        return Channel::where('phone_number_id', $phoneNumberId)
            ->where('status', 'connected')
            ->first();
    }
}
