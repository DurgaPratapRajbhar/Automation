<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) New column + UNIQUE index (unique = index + no duplicates)
        Schema::table('channels', function (Blueprint $table) {
            $table->string('phone_number_id')->nullable()->unique()->after('provider');
        });

        // 2) Copy existing numbers out of the JSON into the new column
        DB::table('channel_credentials')
            ->whereNotNull('config')
            ->orderBy('id')
            ->each(function ($row) {
                $config = json_decode($row->config, true) ?? [];

                if (! empty($config['phone_number_id'])) {
                    DB::table('channels')
                        ->where('id', $row->channel_id)
                        ->update(['phone_number_id' => $config['phone_number_id']]);
                }
            });
    }

    public function down(): void
    {

        // $table->dropColumn('phone_number_id');

    }
};
