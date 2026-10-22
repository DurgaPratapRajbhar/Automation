<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permissions app ka fixed data hai -> migrate chalte hi ban jayein, seeder ki zaroorat nahi.
 * upsert = dobara chale to duplicate nahi banega.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->upsert(
            array_map(fn (array $permission) => [
                ...$permission,
                'created_at' => $now,
                'updated_at' => $now,
            ], PermissionCatalog::all()),
            ['slug'],
            ['name', 'updated_at']
        );
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('slug', array_column(PermissionCatalog::all(), 'slug'))
            ->delete();
    }
};
