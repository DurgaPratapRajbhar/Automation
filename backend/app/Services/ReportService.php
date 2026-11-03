<?php

namespace App\Services;

use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;

// The daily report as a short WhatsApp text: sales of today, and what needs the owner
class ReportService
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaces,
        private OrderRepositoryInterface $orders,
        private DailySummaryService $summary,
    ) {}

    public function build(int $workspaceId): string
    {
        $now = now(config('promises.timezone'));
        $sales = $this->orders->confirmedBetween($workspaceId, $now->copy()->startOfDay(), $now);
        $summary = $this->summary->get($workspaceId);

        return __('reports.daily', [
            'business' => $this->workspaces->findById($workspaceId)->name,
            'orders' => $sales['count'],
            'sales' => number_format($sales['total'], 2),
            'overdue' => $summary['overdue'],
            'today' => $summary['today'],
            'hot' => $summary['hot_leads'],
            'ok' => $summary['needs_ok'],
            'unread' => $summary['unread'],
        ]);
    }
}
