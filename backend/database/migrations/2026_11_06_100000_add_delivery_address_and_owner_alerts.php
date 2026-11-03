<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // The business delivers: the Orders assistant asks for the address before it drafts an order
            $table->boolean('ask_delivery_address')->default(false)->after('lead_questions');
            // A WhatsApp message to the owner's own number for a new order and a hot lead
            $table->boolean('owner_alerts')->default(true)->after('ask_delivery_address');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('address', 500)->nullable()->after('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('address'));
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn(['ask_delivery_address', 'owner_alerts']));
    }
};
