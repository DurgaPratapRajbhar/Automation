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
        Schema::create('channels', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workspace_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type');
            // whatsapp, instagram, facebook, google_business

            $table->string('provider');
            // meta, google

            $table->string('name')->nullable();

            $table->string('external_account_id')->nullable();

            $table->string('status')->default('disconnected');
            // disconnected, connected, error

            $table->timestamps();

            $table->index(['workspace_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
