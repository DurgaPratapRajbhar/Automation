<?php

namespace App\Repositories\Contracts;

use App\Models\Channel;
use Illuminate\Support\Collection;

interface ChannelRepositoryInterface
{
    public function getByWorkspaceId(int $workspaceId): Collection;

    public function findInWorkspace(int $workspaceId, int $channelId): ?Channel;

    // Sirf diye gaye workspace ka connected channel, credential ke saath
    public function findConnectedInWorkspace(int $workspaceId, int $channelId): ?Channel;

    public function create(array $channelData, array $credentialData): Channel;

    public function update(Channel $channel, array $channelData, array $credentialData): Channel;

    public function delete(Channel $channel): void;

    // Ek WhatsApp number poore system me ek hi channel se juda ho (webhook routing isi se hogi)
    public function phoneNumberIdExists(string $phoneNumberId, ?int $ignoreChannelId = null): bool;

    // Incoming webhook: which channel owns this WhatsApp number?
    public function findConnectedByPhoneNumberId(string $phoneNumberId): ?Channel;
}
