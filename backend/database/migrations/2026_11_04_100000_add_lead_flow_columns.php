<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // The Sales agent asks a new lead these questions (up to 3), one at a time
            $table->boolean('lead_flow_enabled')->default(false)->after('inbox_language');
            $table->json('lead_questions')->nullable()->after('lead_flow_enabled');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->string('lead_status', 20)->nullable()->after('customer_language');
            $table->json('lead_answers')->nullable()->after('lead_status');
            $table->string('lead_summary', 500)->nullable()->after('lead_answers');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn(['lead_status', 'lead_answers', 'lead_summary']));
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn(['lead_flow_enabled', 'lead_questions']));
    }
};
