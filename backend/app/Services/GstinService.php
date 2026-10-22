<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;

/**
 * The first 2 digits of a GSTIN are the state code.
 * Used by every place that stores a GSTIN (customers, workspace, later suppliers).
 */
class GstinService
{
    /**
     * Upper-cases the GSTIN, fills state_code from it, or rejects a mismatch.
     * Expects the keys 'gstin' and 'state_code' when present.
     */
    public function applyStateCode(array $data): array
    {
        if (empty($data['gstin'])) {
            return $data;
        }

        $data['gstin'] = strtoupper($data['gstin']);
        $gstinState = substr($data['gstin'], 0, 2);

        if (empty($data['state_code'])) {
            $data['state_code'] = $gstinState;
        } elseif ($data['state_code'] !== $gstinState) {
            throw new BusinessRuleException(__('rules.gstin_state_mismatch'));
        }

        return $data;
    }
}
