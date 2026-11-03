<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // The language the business owner reads and writes in; chats are shown (and answered) in it
            $table->string('inbox_language', 5)->default(config('translation.default_inbox_language'))->after('reply_style');
        });

        Schema::table('conversations', function (Blueprint $table) {
            // The language the customer writes in, found from their last message
            $table->string('customer_language', 5)->nullable()->after('customer_name');
        });

        Schema::table('messages', function (Blueprint $table) {
            // language = the language of body. translation = the same message in the business's inbox language
            // (for a message we sent in the customer's language: what the owner originally wrote)
            $table->string('language', 5)->nullable()->after('body');
            $table->text('translation')->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn(['language', 'translation']));
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn('customer_language'));
        Schema::table('workspaces', fn (Blueprint $table) => $table->dropColumn('inbox_language'));
    }
};
