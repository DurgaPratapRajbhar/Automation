<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            // Copied from the chat, so the promise stays readable if the chat is removed
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->string('kind', 20);
            $table->string('quote', 500);
            $table->decimal('amount', 12, 2)->nullable();
            $table->date('due_on');
            $table->string('status', 20)->default('open');
            $table->string('source', 20);
            $table->timestamps();

            // The Promises page: open promises by date
            $table->index(['workspace_id', 'status', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promises');
    }
};
