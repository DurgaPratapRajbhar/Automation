<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jo jaankari shop apni dukaan ke baare me deti hai (text ya file).
        // Aage chal ke AI isi ko padh ke customer ko jawaab dega (embedding).
        Schema::create('knowledge_sources', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workspace_id')
                ->constrained()
                ->cascadeOnDelete();

            // text = textbox me likha, file = upload kiya document
            $table->string('type', 10);

            $table->string('title', 150);

            // text source ka body; file source me null
            $table->text('content')->nullable();

            // Uploaded file ki details (private disk par stored)
            $table->string('file_path', 255)->nullable();
            $table->string('file_name', 255)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('size_bytes')->nullable();

            // pending = AI ne abhi padha nahi; ready = padh liya (AI step me set hoga)
            $table->string('status', 10)->default('pending');

            $table->timestamps();

            $table->index(['workspace_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_sources');
    }
};
