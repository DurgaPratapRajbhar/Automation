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
            // The other columns of the Excel a business uploaded (amount, date, city...), keyed by the column title
            $table->json('data')->nullable()->after('whatsapp_opt_in');

            // The contact asked us to stop (STOP / BAND): never message them in a campaign again
            $table->timestamp('opted_out_at')->nullable()->after('data');
        });

        // New modules must not switch themselves on for shops that existed before them: write down what each had
        DB::table('workspaces')->whereNull('modules')->update([
            'modules' => json_encode(['inbox', 'whatsapp', 'knowledge', 'customers', 'team']),
        ]);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['data', 'opted_out_at']);
        });
    }
};
