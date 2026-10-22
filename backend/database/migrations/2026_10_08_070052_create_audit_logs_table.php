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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workspace_id')
                ->constrained()
                ->cascadeOnDelete();

            // Who did it. If that user is deleted later, keep the history (set NULL)
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action');                  // e.g. member.role_changed
            $table->string('subject_type');            // e.g. member, role, channel
            $table->unsignedBigInteger('subject_id');  // e.g. member's user id

            $table->json('changes')->nullable();       // e.g. {"old_role":"Viewer","new_role":"Sales Staff"}

            // History is never edited, so only created_at
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
