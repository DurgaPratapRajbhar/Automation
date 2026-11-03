<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Contacts\PhoneNormalizer;
use Throwable;

// A WhatsApp message to the business owner's own number (the phone saved in Business profile): reports and alerts
class OwnerNotifier
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaces,
        private ChannelRepositoryInterface $channels,
        private ChannelService $channelService,
        private PhoneNormalizer $phones,
    ) {}

    public function phone(int $workspaceId): ?string
    {
        $raw = $this->workspaces->findById($workspaceId)?->phone;

        return $raw ? $this->phones->normalize($raw) : null;
    }

    /**
     * Sends the text and returns WhatsApp's answer. Throws when there is no number to send to, no connected WhatsApp,
     * or WhatsApp refuses.
     */
    public function send(int $workspaceId, string $text): array
    {
        $phone = $this->phone($workspaceId) ?? throw new BusinessRuleException(__('actions.no_owner_number'), 422);
        $channel = $this->channels->getByWorkspaceId($workspaceId)->firstWhere('status', 'connected')
            ?? throw new BusinessRuleException(__('actions.no_channel'), 422);

        return $this->channelService->sendMessage($workspaceId, $channel->id, $phone, $text);
    }

    /**
     * An alert for the owner (a new order, a hot lead). Never breaks the work that caused it: it is also in the app,
     * and WhatsApp may refuse it (for example when the owner has not written to the business number for a day).
     */
    public function notify(int $workspaceId, string $text): void
    {
        if (! $this->workspaces->findById($workspaceId)?->owner_alerts) {
            return;
        }

        try {
            $this->send($workspaceId, $text);
        } catch (Throwable $e) {
            logger()->info('Owner alert not sent', ['workspace_id' => $workspaceId, 'reason' => $e->getMessage()]);
        }
    }
}
