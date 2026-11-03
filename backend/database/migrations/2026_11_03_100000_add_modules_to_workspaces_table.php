<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // Modules the super admin switched on, e.g. ["inbox","whatsapp"]. null = every module (shops made before this existed).
            $table->json('modules')->nullable()->after('reply_style');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('modules');
        });
    }
};
