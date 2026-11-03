<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // When and how the person agreed to get marketing messages (proof, if anyone ever asks)
            $table->timestamp('whatsapp_opt_in_at')->nullable()->after('whatsapp_opt_in');
            $table->string('whatsapp_opt_in_source', 20)->nullable()->after('whatsapp_opt_in_at');
        });

        // Permissions given before this record existed: the date is not known, so it says so
        DB::table('customers')->where('whatsapp_opt_in', true)->update(['whatsapp_opt_in_at' => DB::raw('created_at'), 'whatsapp_opt_in_source' => 'earlier']);

        Schema::table('orders', function (Blueprint $table) {
            // null = not on its way yet; on_the_way; delivered
            $table->string('delivery_status', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('delivery_status'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn(['whatsapp_opt_in_at', 'whatsapp_opt_in_source']));
    }
};
