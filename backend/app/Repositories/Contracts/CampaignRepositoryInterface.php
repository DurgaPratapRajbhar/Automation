<?php

namespace App\Repositories\Contracts;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

interface CampaignRepositoryInterface
{
    public function create(array $data): Campaign;

    // $rows = [['customer_id' => .., 'phone' => .., 'parameters' => [..]], ...]
    public function addRecipients(Campaign $campaign, array $rows): void;

    public function findInWorkspace(int $workspaceId, int $campaignId): ?Campaign;

    public function cursorForWorkspace(int $workspaceId, int $perPage): CursorPaginator;

    public function setStatus(Campaign $campaign, CampaignStatus $status): void;

    // Recipients still waiting to be sent, oldest first
    public function queuedRecipientIds(Campaign $campaign): array;

    public function hasQueued(Campaign $campaign): bool;

    public function findRecipient(int $recipientId): ?CampaignRecipient;

    public function updateRecipient(CampaignRecipient $recipient, array $data): void;

    public function sampleRecipients(Campaign $campaign, int $limit): Collection;

    // People whose message did not go (refused straight away, or reported failed later), with the reason
    public function failedRecipients(Campaign $campaign, int $limit): Collection;

    // Counts for the report: total, queued, sent, delivered, read, replied, failed, skipped
    public function report(Campaign $campaign): array;
}
