<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();

            $table->string('customer_phone');            // WhatsApp wa_id, e.g. 918898540057
            $table->string('customer_name')->nullable(); // WhatsApp profile name

            $table->string('status')->default('open');   // open, closed
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            // One chat per customer per channel
            $table->unique(['channel_id', 'customer_phone']);
            // Inbox list: latest chats first
            $table->index(['workspace_id', 'last_message_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
