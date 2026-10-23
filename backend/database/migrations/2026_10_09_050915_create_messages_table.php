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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->string('direction');                 // inbound (customer → us), outbound (us → customer)
            $table->string('external_id')->nullable()->unique(); // WhatsApp message id: wamid.xxx

            $table->string('type')->default('text');     // text, image, audio...
            $table->text('body')->nullable();            // message text
            $table->json('payload')->nullable();         // raw data from Meta (for debugging)

            $table->string('status')->default('received'); // received, sent, delivered, read, failed

            // Who replied: a staff member (user id), or NULL if AI/bot
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Chat screen: messages of one conversation in order
            $table->index(['conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
