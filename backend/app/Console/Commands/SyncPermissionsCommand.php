<?php

namespace App\Console\Commands;

use App\Services\PermissionSyncService;
use Illuminate\Console\Command;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync';

    protected $description = 'Make the permissions table match App\Enums\Permission';

    public function handle(PermissionSyncService $permissionSync): int
    {
        $result = $permissionSync->sync();

        $this->info("Permissions synced: {$result['created']} created, {$result['deleted']} deleted.");

        return self::SUCCESS;
    }
}
