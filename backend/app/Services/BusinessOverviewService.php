<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Product;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;

// The Dashboard's business numbers, and what is running by itself, for any kind of business
class BusinessOverviewService
{
    public const ACTIVE = 'active';

    public const NEEDS_OK = 'needs_ok';

    public const OFF = 'off';

    public function __construct(
        private CustomerRepositoryInterface $customers,
        private CampaignRepositoryInterface $campaigns,
        private OrderRepositoryInterface $orders,
        private ProductRepositoryInterface $products,
        private ChannelRepositoryInterface $channels,
        private WorkspaceRepositoryInterface $workspaces,
    ) {}

    /** @return array{stats: array<string, int>, workflows: array<int, array{key: string, state: string, href: string}>} */
    public function get(int $workspaceId): array
    {
        $now = now(config('promises.timezone'));
        $sales = $this->orders->confirmedBetween($workspaceId, $now->copy()->startOfMonth(), $now);

        return [
            'stats' => [
                'customers' => $this->customers->countForWorkspace($workspaceId),
                'campaign_messages' => $this->campaigns->sentSince($workspaceId, $now->copy()->subDays(30)),
                'orders_this_month' => $sales['count'],
            ],
            'workflows' => $this->workflows($workspaceId),
        ];
    }

    // What the business has switched on, in plain states: running, waiting for the owner, or not set up
    private function workflows(int $workspaceId): array
    {
        $workspace = $this->workspaces->findById($workspaceId);
        $products = $this->products->activeForWorkspace($workspaceId);
        $connected = $this->channels->getByWorkspaceId($workspaceId)->contains('status', 'connected');

        $workflows = [
            ['key' => 'assistant', 'state' => $connected ? self::ACTIVE : self::OFF, 'href' => '/settings/whatsapp'],
            ['key' => 'orders', 'state' => $products->isNotEmpty() ? self::ACTIVE : self::OFF, 'href' => '/products'],
        ];

        if ($products->contains(fn (Product $product) => $product->duration_minutes)) {
            $workflows[] = ['key' => 'follow_ups', 'state' => $workspace->auto_booking_followups ? self::ACTIVE : self::NEEDS_OK, 'href' => '/settings/profile'];
        }

        $workflows[] = ['key' => 'leads', 'state' => $workspace->lead_flow_enabled ? self::ACTIVE : self::OFF, 'href' => '/settings/profile'];
        $workflows[] = ['key' => 'campaign', 'state' => $this->campaignState($workspaceId), 'href' => '/campaigns'];

        return $workflows;
    }

    private function campaignState(int $workspaceId): string
    {
        return match (true) {
            $this->campaigns->countByStatus($workspaceId, CampaignStatus::Sending) > 0 => self::ACTIVE,
            $this->campaigns->countByStatus($workspaceId, CampaignStatus::Draft) > 0 => self::NEEDS_OK,
            default => self::OFF,
        };
    }
}
