<?php

namespace App\Repositories\Eloquent;

use App\Enums\CampaignStatus;
use App\Enums\RecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampaignRepository implements CampaignRepositoryInterface
{
    // Rows per insert, so a big list does not make one huge query
    private const INSERT_CHUNK = 500;

    public function create(array $data): Campaign
    {
        return Campaign::create($data);
    }

    public function addRecipients(Campaign $campaign, array $rows): void
    {
        $now = now();

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            CampaignRecipient::insert(array_map(fn (array $row) => [
                'campaign_id' => $campaign->id,
                'customer_id' => $row['customer_id'],
                'phone' => $row['phone'],
                'parameters' => json_encode($row['parameters']),
                'status' => RecipientStatus::Queued->value,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }
    }

    public function findInWorkspace(int $workspaceId, int $campaignId): ?Campaign
    {
        return Campaign::where('workspace_id', $workspaceId)->find($campaignId);
    }

    public function cursorForWorkspace(int $workspaceId, int $perPage): CursorPaginator
    {
        return Campaign::where('workspace_id', $workspaceId)->orderByDesc('id')->cursorPaginate($perPage);
    }

    public function setStatus(Campaign $campaign, CampaignStatus $status): void
    {
        $changes = ['status' => $status->value];

        if ($status === CampaignStatus::Sending) {
            $changes['started_at'] = now();
        }
        if ($status === CampaignStatus::Done || $status === CampaignStatus::Cancelled) {
            $changes['finished_at'] = now();
        }

        $campaign->update($changes);
    }

    public function queuedRecipientIds(Campaign $campaign): array
    {
        return CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', RecipientStatus::Queued->value)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    public function hasQueued(Campaign $campaign): bool
    {
        return CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', RecipientStatus::Queued->value)
            ->exists();
    }

    public function findRecipient(int $recipientId): ?CampaignRecipient
    {
        return CampaignRecipient::with(['campaign', 'customer'])->find($recipientId);
    }

    public function updateRecipient(CampaignRecipient $recipient, array $data): void
    {
        $recipient->update($data);
    }

    public function sampleRecipients(Campaign $campaign, int $limit): Collection
    {
        return CampaignRecipient::with('customer:id,name')
            ->where('campaign_id', $campaign->id)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function report(Campaign $campaign): array
    {
        // One pass over the recipients and the message each one got. "replied" = the person wrote back after it.
        $row = DB::selectOne("
            SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN r.status = 'queued' THEN 1 ELSE 0 END), 0) AS queued,
                COALESCE(SUM(CASE WHEN r.status = 'sent' AND m.status <> 'failed' THEN 1 ELSE 0 END), 0) AS sent,
                COALESCE(SUM(CASE WHEN m.status IN ('delivered', 'read') THEN 1 ELSE 0 END), 0) AS delivered,
                COALESCE(SUM(CASE WHEN m.status = 'read' THEN 1 ELSE 0 END), 0) AS \"read\",
                COALESCE(SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM messages i
                    WHERE i.conversation_id = m.conversation_id AND i.direction = 'inbound' AND i.id > m.id
                ) THEN 1 ELSE 0 END), 0) AS replied,
                COALESCE(SUM(CASE WHEN r.status = 'failed' OR m.status = 'failed' THEN 1 ELSE 0 END), 0) AS failed,
                COALESCE(SUM(CASE WHEN r.status = 'skipped' THEN 1 ELSE 0 END), 0) AS skipped
            FROM campaign_recipients r
            LEFT JOIN messages m ON m.id = r.message_id
            WHERE r.campaign_id = ?
        ", [$campaign->id]);

        return array_map('intval', (array) $row);
    }
}
