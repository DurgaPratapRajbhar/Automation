<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Set for a service that is booked for a time (a haircut, a consultation, a class): how long it takes
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('stock');
        });

        Schema::table('workspaces', function (Blueprint $table) {
            // When bookings can be made, in the business's own time
            $table->string('booking_open_time', 5)->default('10:00')->after('owner_alerts');
            $table->string('booking_close_time', 5)->default('19:00')->after('booking_open_time');
            $table->json('booking_closed_days')->nullable()->after('booking_close_time');
            // How many bookings can be at the same time (chairs, doctors, tables, staff)
            $table->unsignedTinyInteger('booking_capacity')->default(1)->after('booking_closed_days');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('booking_at');
            $table->timestamp('reminded_at')->nullable()->after('duration_minutes');
            $table->timestamp('feedback_asked_at')->nullable()->after('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['duration_minutes', 'reminded_at', 'feedback_asked_at']));
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn(['booking_open_time', 'booking_close_time', 'booking_closed_days', 'booking_capacity']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('duration_minutes'));
    }
};
