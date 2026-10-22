<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workspace_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name', 100);
            $table->string('phone', 15)->nullable();
            $table->string('address', 255)->nullable();

            // For B2B invoices; state_code decides CGST/SGST vs IGST
            $table->string('gstin', 15)->nullable();
            $table->string('state_code', 2)->nullable();

            // Credit (udhaar) limit; null means no limit
            $table->decimal('credit_limit', 12, 2)->nullable();

            // Customer agreed to receive WhatsApp messages
            $table->boolean('whatsapp_opt_in')->default(false);

            $table->timestamps();

            // One phone number belongs to one customer per workspace
            $table->unique(['workspace_id', 'phone']);
            $table->index(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
