<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // null = not a trial (paid or created by the admin)
            $table->timestamp('trial_ends_at')->nullable()->after('reply_style');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id', 64)->nullable()->unique()->after('email');
            // false for accounts made with Google: they have a random password nobody knows
            $table->boolean('has_password')->default(true)->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'has_password']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('trial_ends_at');
        });
    }
};
