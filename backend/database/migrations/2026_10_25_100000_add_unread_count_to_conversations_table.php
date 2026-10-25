<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Customer ke kitne message abhi tak dukaan ne nahi dekhe (Inbox badge)
            $table->unsignedInteger('unread_count')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('unread_count');
        });
    }
};
