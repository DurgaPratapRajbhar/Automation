<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // When the owner said yes: sales are counted by this, not by the last change (paid, delivered...)
            $table->timestamp('confirmed_at')->nullable()->after('status');
        });

        DB::table('orders')->where('status', 'confirmed')->update(['confirmed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('confirmed_at'));
    }
};
