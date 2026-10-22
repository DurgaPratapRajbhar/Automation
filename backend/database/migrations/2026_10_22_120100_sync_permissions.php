<?php

use App\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;

/**
 * Fills the permissions table from the protected routes on a fresh database.
 * After adding or removing routes run `php artisan permissions:sync`.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionSyncService::class)->sync();
    }

    public function down(): void
    {
        // Tables are dropped by the permission tables migration
    }
};
