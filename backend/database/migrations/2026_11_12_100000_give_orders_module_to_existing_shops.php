<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Orders, products, promises and approvals used to come with Inbox and Knowledge. They are a module of their own now
// (a new shop starts without them), so the shops that already have Inbox or Knowledge keep what they had.
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('workspaces')->whereNotNull('modules')->get(['id', 'modules']) as $workspace) {
            $modules = json_decode($workspace->modules, true) ?: [];

            if (array_intersect(['inbox', 'knowledge'], $modules) && ! in_array('orders', $modules, true)) {
                DB::table('workspaces')->where('id', $workspace->id)->update(['modules' => json_encode([...$modules, 'orders'])]);
            }
        }
    }

    public function down(): void {}
};
