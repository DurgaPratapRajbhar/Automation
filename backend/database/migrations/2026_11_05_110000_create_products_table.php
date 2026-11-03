<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 12, 2);
            // "kg", "piece", "hour", "session"...
            $table->string('unit', 30)->nullable();
            // null = stock is not counted (a service, or the owner does not track it)
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
