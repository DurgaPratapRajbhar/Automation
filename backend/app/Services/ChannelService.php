<?php

namespace App\Services;

use App\Factories\ChannelProviderFactory;
use App\Models\Channel;
use RuntimeException;

class ChannelService
{
    public function __construct(
        private ChannelProviderFactory $providerFactory
    ) {}

    public function sendMessage(
        int $workspaceId,
        int $channelId,
        string $recipient,
        string $message
    ): array {

        $channel = Channel::with('credential')
            ->where('workspace_id', $workspaceId)   // sirf apne workspace ka channel
            ->where('status', 'connected')
            ->findOrFail($channelId);

        $credential = $channel->credential;

        if (!$credential || !$credential->access_token) {
            throw new RuntimeException('Channel credentials are missing.');
        }

        $provider = $this->providerFactory->make(
            $channel->type,
            $channel->provider
        );

        return $provider->sendMessage(
            [
                'access_token' => $credential->access_token,
                ...($credential->config ?? []),
            ],
            $recipient,
            $message
        );
    }
}