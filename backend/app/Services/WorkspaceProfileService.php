<?php

namespace App\Services;

use App\Enums\GstStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;

/**
 * The shop's own details: contact, GST and UPI (used later on bills and payment links).
 */
class WorkspaceProfileService
{
    public function __construct(
        private WorkspaceRepositoryInterface $workspaceRepository,
        private GstinService $gstinService
    ) {}

    public function get(Workspace $workspace): array
    {
        return $this->present($workspace);
    }

    public function update(Workspace $workspace, array $data): array
    {
        // Check GST fields together, using stored values for the ones not sent
        $gst = $this->gstinService->applyStateCode([
            'gst_status' => $workspace->gst_status,
            'gstin' => $workspace->gstin,
            'state_code' => $workspace->state_code,
            ...array_intersect_key($data, array_flip(['gst_status', 'gstin', 'state_code'])),
        ]);

        if ($gst['gst_status'] !== GstStatus::Unregistered->value && empty($gst['gstin'])) {
            throw new BusinessRuleException(__('workspace.gstin_required'));
        }

        return $this->present($this->workspaceRepository->update($workspace, [...$data, ...$gst]));
    }

    private function present(Workspace $workspace): array
    {
        return [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'type' => $workspace->type,
            'status' => $workspace->status,
            'phone' => $workspace->phone,
            'address' => $workspace->address,
            'state_code' => $workspace->state_code,
            'gst_status' => $workspace->gst_status,
            'gstin' => $workspace->gstin,
            'upi_id' => $workspace->upi_id,
        ];
    }
}
