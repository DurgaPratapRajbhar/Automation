<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // The owner says yes once: booking reminders and feedback requests go out without asking each time
            $table->boolean('auto_booking_followups')->default(false)->after('booking_capacity');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn('auto_booking_followups'));
    }
};
