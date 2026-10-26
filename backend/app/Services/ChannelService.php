<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Factories\ChannelProviderFactory;
use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;

class ChannelService
{
    public function __construct(
        private ChannelRepositoryInterface $channelRepository,
        private ChannelProviderFactory $providerFactory
    ) {}

    public function getChannels(int $workspaceId): array
    {
        return $this->channelRepository
            ->getByWorkspaceId($workspaceId)
            ->map(fn (Channel $channel) => $this->present($channel))
            ->all();
    }

    public function getChannel(int $workspaceId, int $channelId): array
    {
        return $this->present($this->findOrFail($workspaceId, $channelId));
    }

    // $extraConfig = internal values only (e.g. the encrypted register PIN), never from the request
    public function create(int $workspaceId, array $data, array $extraConfig = []): array
    {
        if (! $this->providerFactory->supports($data['type'], $data['provider'])) {
            throw new BusinessRuleException("Unsupported channel: {$data['provider']}.{$data['type']}");
        }

        $credentials = $data['credentials'];

        $this->ensurePhoneNumberIdAvailable($credentials['phone_number_id']);

        $channel = $this->channelRepository->create(
            [
                'workspace_id' => $workspaceId,
                'type' => $data['type'],
                'provider' => $data['provider'],
                'name' => $data['name'],
                'external_account_id' => $credentials['waba_id'] ?? null,
                'status' => 'connected',
                'phone_number_id' => $credentials['phone_number_id'],
            ],
            [
                'access_token' => $credentials['access_token'],
                'config' => [
                    'phone_number_id' => $credentials['phone_number_id'],
                    'waba_id' => $credentials['waba_id'] ?? null,
                    ...$extraConfig,
                ],
            ]
        );

        return $this->present($channel);
    }

    public function update(int $workspaceId, int $channelId, array $data): array
    {
        $channel = $this->findOrFail($workspaceId, $channelId);

        $channelData = array_intersect_key($data, array_flip(['name', 'status']));
        $credentialData = [];

        if (isset($data['credentials'])) {
            $incoming = $data['credentials'];
            $config = $channel->credential?->config ?? [];

            if (isset($incoming['phone_number_id'])) {
                $this->ensurePhoneNumberIdAvailable($incoming['phone_number_id'], $channel->id);
                $config['phone_number_id'] = $incoming['phone_number_id'];
                $channelData['phone_number_id'] = $incoming['phone_number_id'];

            }

            if (array_key_exists('waba_id', $incoming)) {
                $config['waba_id'] = $incoming['waba_id'];
                $channelData['external_account_id'] = $incoming['waba_id'];
            }

            $credentialData['config'] = $config;

            if (isset($incoming['access_token'])) {
                $credentialData['access_token'] = $incoming['access_token'];
            }
        }

        return $this->present(
            $this->channelRepository->update($channel, $channelData, $credentialData)
        );
    }

    public function delete(int $workspaceId, int $channelId): void
    {
        $this->channelRepository->delete(
            $this->findOrFail($workspaceId, $channelId)
        );
    }

    public function sendMessage(
        int $workspaceId,
        int $channelId,
        string $recipient,
        string $message
    ): array {
        [$provider, $credentials] = $this->providerFor($workspaceId, $channelId);

        return $provider->sendMessage($credentials, $recipient, $message);
    }

    public function sendTemplate(
        int $workspaceId,
        int $channelId,
        string $recipient,
        string $templateName,
        string $language,
        array $parameters
    ): array {
        [$provider, $credentials] = $this->providerFor($workspaceId, $channelId);

        return $provider->sendTemplate($credentials, $recipient, $templateName, $language, $parameters);
    }

    // The provider strategy + credentials of a connected channel in this workspace
    private function providerFor(int $workspaceId, int $channelId): array
    {
        $channel = $this->channelRepository
            ->findConnectedInWorkspace($workspaceId, $channelId);

        if (! $channel) {
            throw new BusinessRuleException('Channel not found.', 404);
        }

        $credential = $channel->credential;

        if (! $credential || ! $credential->access_token) {
            throw new BusinessRuleException('Channel credentials are missing.');
        }

        $provider = $this->providerFactory->make(
            $channel->type,
            $channel->provider
        );

        return [$provider, ['access_token' => $credential->access_token, ...($credential->config ?? [])]];
    }

    public function ensurePhoneNumberIdAvailable(string $phoneNumberId, ?int $ignoreChannelId = null): void
    {
        if ($this->channelRepository->phoneNumberIdExists($phoneNumberId, $ignoreChannelId)) {
            throw new BusinessRuleException('This WhatsApp number is already connected to a channel.');
        }
    }

    private function findOrFail(int $workspaceId, int $channelId): Channel
    {
        $channel = $this->channelRepository->findInWorkspace($workspaceId, $channelId);

        if (! $channel) {
            throw new BusinessRuleException('Channel not found.', 404);
        }

        return $channel;
    }

    // Access token kabhi response me nahi jaata — sirf "hai ya nahi"
    private function present(Channel $channel): array
    {
        $config = $channel->credential?->config ?? [];

        return [
            'id' => $channel->id,
            'type' => $channel->type,
            'provider' => $channel->provider,
            'name' => $channel->name,
            'status' => $channel->status,
            'phone_number_id' => $config['phone_number_id'] ?? null,
            'waba_id' => $config['waba_id'] ?? null,
            'has_access_token' => (bool) $channel->credential?->access_token,
            'created_at' => $channel->created_at,
            'updated_at' => $channel->updated_at,
        ];
    }
}
