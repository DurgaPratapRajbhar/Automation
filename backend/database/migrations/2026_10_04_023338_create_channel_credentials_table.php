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
        Schema::create('channel_credentials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('channel_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();

            $table->timestamp('expires_at')->nullable();

            $table->json('config')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_credentials');
    }
};
