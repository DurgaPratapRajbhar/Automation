<?php

namespace App\Services;

use App\Enums\WorkspaceStatus;
use App\Models\Workspace;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use Illuminate\Support\Str;

/**
 * One API key per workspace, so the shop's own website can use the same AI (POST /api/v1/ask).
 * Only a hash is stored: the full key is returned once, when it is made.
 */
class WorkspaceApiKeyService
{
    private const PREFIX = 'sdp_';

    public function __construct(
        private WorkspaceRepositoryInterface $workspaceRepository
    ) {}

    public function status(Workspace $workspace): array
    {
        return [
            'has_key' => $workspace->api_key_hash !== null,
            'hint' => $workspace->api_key_hint,
        ];
    }

    // A new key replaces the old one, which stops working at once
    public function regenerate(Workspace $workspace): array
    {
        $key = self::PREFIX.Str::random(40);

        $workspace = $this->workspaceRepository->update($workspace, [
            'api_key_hash' => $this->hash($key),
            'api_key_hint' => substr($key, -4),
        ]);

        return ['key' => $key, ...$this->status($workspace)];
    }

    public function revoke(Workspace $workspace): array
    {
        return $this->status($this->workspaceRepository->update($workspace, [
            'api_key_hash' => null,
            'api_key_hint' => null,
        ]));
    }

    // The workspace a key belongs to; null for an unknown key or a suspended shop
    public function findActiveWorkspace(?string $key): ?Workspace
    {
        if (! $key || ! str_starts_with($key, self::PREFIX)) {
            return null;
        }

        $workspace = $this->workspaceRepository->findByApiKeyHash($this->hash($key));

        return $workspace?->status === WorkspaceStatus::Active->value && ! $workspace->trialExpired() ? $workspace : null;
    }

    private function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
