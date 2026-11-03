<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name', 100);

            // The approved WhatsApp template, and a copy of its text as it was when the campaign was made
            $table->string('template_name', 512);
            $table->string('template_language', 10);
            $table->text('template_body');

            // What goes into each {{1}}, {{2}}...: [{"source":"name"}, {"source":"column","value":"Fees Due"}, {"source":"text","value":"..."}]
            $table->json('parameters');

            $table->string('status', 20)->default('draft'); // draft, sending, done, cancelled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('phone', 15);
            // The values for the blanks, worked out when the campaign was made
            $table->json('parameters');

            // queued -> sent (handed to WhatsApp) | failed | skipped (e.g. the person opted out meanwhile)
            $table->string('status', 20)->default('queued');
            $table->string('error', 500)->nullable();

            // The message in the Inbox; its own status then goes sent -> delivered -> read
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            // One person once per campaign
            $table->unique(['campaign_id', 'customer_id']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
