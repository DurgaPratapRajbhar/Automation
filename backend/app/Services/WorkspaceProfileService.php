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
        // GST fields are checked together (stored values for the ones not sent), only when one of them is being changed
        $gstChanges = array_intersect_key($data, array_flip(['gst_status', 'gstin', 'state_code']));

        if ($gstChanges) {
            $gst = $this->gstinService->applyStateCode([
                'gst_status' => $workspace->gst_status,
                'gstin' => $workspace->gstin,
                'state_code' => $workspace->state_code,
                ...$gstChanges,
            ]);

            if ($gst['gst_status'] !== GstStatus::Unregistered->value && empty($gst['gstin'])) {
                throw new BusinessRuleException(__('workspace.gstin_required'));
            }

            $data = [...$data, ...$gst];
        }

        if (array_key_exists('lead_questions', $data)) {
            $data['lead_questions'] = array_values(array_filter(array_map('trim', $data['lead_questions'] ?? [])));
        }

        return $this->present($this->workspaceRepository->update($workspace, $data));
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
            'reply_style' => $workspace->reply_style,
            'inbox_language' => $workspace->inbox_language,
            'languages' => config('translation.languages'),
            'ask_delivery_address' => $workspace->ask_delivery_address,
            'owner_alerts' => $workspace->owner_alerts,
            'booking_open_time' => $workspace->booking_open_time,
            'booking_close_time' => $workspace->booking_close_time,
            'booking_closed_days' => $workspace->booking_closed_days ?? [],
            'booking_capacity' => $workspace->booking_capacity,
            'auto_booking_followups' => $workspace->auto_booking_followups,
            'booking_days' => config('bookings.days'),
            'lead_flow_enabled' => $workspace->lead_flow_enabled,
            'lead_questions' => $workspace->lead_questions ?? [],
            'max_lead_questions' => config('leads.max_questions'),
        ];
    }
}
