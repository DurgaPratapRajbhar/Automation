<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // Only the SHA-256 of the key is stored; the key itself is shown once
            $table->string('api_key_hash', 64)->nullable()->unique()->after('upi_id');
            // Last characters, so the owner can tell which key is in use
            $table->string('api_key_hint', 4)->nullable()->after('api_key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropUnique(['api_key_hash']);
            $table->dropColumn(['api_key_hash', 'api_key_hint']);
        });
    }
};
