<?php

use App\Enums\GstStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('phone', 15)->nullable()->after('type');
            $table->string('address', 255)->nullable()->after('phone');
            $table->string('state_code', 2)->nullable()->after('address');
            $table->string('gst_status', 20)->default(GstStatus::Unregistered->value)->after('state_code');
            $table->string('gstin', 15)->nullable()->after('gst_status');
            // Used to build UPI payment links for customers
            $table->string('upi_id', 100)->nullable()->after('gstin');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address', 'state_code', 'gst_status', 'gstin', 'upi_id']);
        });
    }
};
